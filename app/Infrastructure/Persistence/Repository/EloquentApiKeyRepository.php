<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Identity\ApiKey\ApiKey;
use App\Domain\Identity\ApiKey\Exception\ApiKeyNotFound;
use App\Domain\Identity\ApiKey\OwnerType;
use App\Domain\Identity\ApiKey\Repository\ApiKeyRepository;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyId;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyOwner;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyPrefix;
use App\Domain\Identity\ApiKey\ValueObject\HashedApiKeySecret;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Model\ApiKeyModel;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Eloquent adapter for ApiKeyRepository. The secret is only ever persisted as a hash; scopes are
 * stored as JSON. save() drains the aggregate's events to the outbox in the caller's transaction.
 * Lookups by prefix back the per-request authentication path.
 */
final readonly class EloquentApiKeyRepository implements ApiKeyRepository
{
    private const int EVENT_VERSION = 1;

    private const string SCHEMA_VERSION = '1.0.0';

    public function __construct(
        private OutboxWriter $outbox,
    ) {}

    public function findByPrefix(ApiKeyPrefix $prefix): ?ApiKey
    {
        return $this->map(ApiKeyModel::query()->where('prefix', $prefix->value)->first());
    }

    public function findById(ApiKeyId $id): ?ApiKey
    {
        return $this->map(ApiKeyModel::query()->find($id->value));
    }

    public function getById(ApiKeyId $id): ApiKey
    {
        return $this->findById($id) ?? throw ApiKeyNotFound::withId($id->value);
    }

    public function existsByPrefix(ApiKeyPrefix $prefix): bool
    {
        return ApiKeyModel::query()->where('prefix', $prefix->value)->exists();
    }

    /** @return list<ApiKey> */
    public function listByOwner(ApiKeyOwner $owner): array
    {
        $keys = [];
        $models = ApiKeyModel::query()
            ->where('owner_type', $owner->type->value)
            ->where('owner_id', $owner->id)
            ->orderByDesc('created_at')
            ->get();

        foreach ($models as $model) {
            $key = $this->map($model);
            if ($key instanceof ApiKey) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function save(ApiKey $key): void
    {
        ApiKeyModel::query()->updateOrCreate(
            ['id' => $key->id->value],
            [
                'prefix' => $key->prefix()->value,
                'secret_hash' => $key->secretHash()->value,
                'name' => $key->name(),
                'owner_type' => $key->owner()->type->value,
                'owner_id' => $key->owner()->id,
                'scopes' => $key->scopes()->toArray(),
                'expires_at' => $key->expiresAt(),
                'last_used_at' => $key->lastUsedAt(),
                'revoked_at' => $key->revokedAt(),
                'created_by' => $key->createdBy(),
                'created_at' => $key->createdAt(),
                'updated_at' => $key->updatedAt(),
            ],
        );

        foreach ($key->releaseEvents() as $event) {
            $this->outbox->write(
                $event->eventType(),
                self::EVENT_VERSION,
                self::SCHEMA_VERSION,
                'ApiKey',
                $key->id->value,
                $event->payload(),
            );
        }
    }

    public function nextIdentity(): ApiKeyId
    {
        return new ApiKeyId((string) new Ulid);
    }

    private function map(?ApiKeyModel $model): ?ApiKey
    {
        if (! $model instanceof ApiKeyModel) {
            return null;
        }

        /** @var list<string> $scopes */
        $scopes = array_values(array_filter($model->scopes, is_string(...)));

        return ApiKey::reconstitute(
            new ApiKeyId($model->id),
            new ApiKeyPrefix($model->prefix),
            HashedApiKeySecret::fromHash($model->secret_hash),
            $model->name,
            new ApiKeyOwner(OwnerType::from($model->owner_type), $model->owner_id),
            ScopeCollection::fromStrings($scopes),
            $this->toImmutable($model->expires_at),
            $this->toImmutable($model->last_used_at),
            $this->toImmutable($model->revoked_at),
            $model->created_by,
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }

    private function toImmutable(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return null;
    }
}
