<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Identity\ServiceAccount\Exception\ServiceAccountNotFound;
use App\Domain\Identity\ServiceAccount\Repository\ServiceAccountRepository;
use App\Domain\Identity\ServiceAccount\ServiceAccount;
use App\Domain\Identity\ServiceAccount\ServiceAccountStatus;
use App\Domain\Identity\ServiceAccount\ValueObject\HashedSecret;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceAccountId;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceName;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Model\ServiceAccountModel;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;
use Symfony\Component\Uid\Ulid;

/**
 * Eloquent adapter for ServiceAccountRepository. The secret is only ever persisted as a hash; the
 * scope set is stored as JSON. save() drains the aggregate's events to the outbox in the caller's
 * transaction, so ServiceAccountCreated/CredentialRotated/Disabled never diverge from the row.
 */
final readonly class EloquentServiceAccountRepository implements ServiceAccountRepository
{
    private const int EVENT_VERSION = 1;

    private const string SCHEMA_VERSION = '1.0.0';

    public function __construct(
        private OutboxWriter $outbox,
    ) {}

    public function findById(ServiceAccountId $id): ?ServiceAccount
    {
        return $this->map(ServiceAccountModel::query()->find($id->value));
    }

    public function findByName(ServiceName $name): ?ServiceAccount
    {
        return $this->map(ServiceAccountModel::query()->where('name', $name->value)->first());
    }

    public function getById(ServiceAccountId $id): ServiceAccount
    {
        return $this->findById($id) ?? throw ServiceAccountNotFound::withId($id->value);
    }

    public function existsByName(ServiceName $name): bool
    {
        return ServiceAccountModel::query()->where('name', $name->value)->exists();
    }

    /** @return list<ServiceAccount> */
    public function all(): array
    {
        $accounts = [];
        foreach (ServiceAccountModel::query()->orderBy('name')->get() as $model) {
            $account = $this->map($model);
            if ($account instanceof ServiceAccount) {
                $accounts[] = $account;
            }
        }

        return $accounts;
    }

    public function save(ServiceAccount $account): void
    {
        ServiceAccountModel::query()->updateOrCreate(
            ['id' => $account->id->value],
            [
                'name' => $account->name()->value,
                'secret_hash' => $account->secretHash()->value,
                'status' => $account->status()->value,
                'scopes' => $account->scopes()->toArray(),
                'created_at' => $account->createdAt(),
                'updated_at' => $account->updatedAt(),
            ],
        );

        foreach ($account->releaseEvents() as $event) {
            $this->outbox->write(
                $event->eventType(),
                self::EVENT_VERSION,
                self::SCHEMA_VERSION,
                'ServiceAccount',
                $account->id->value,
                $event->payload(),
            );
        }
    }

    public function nextIdentity(): ServiceAccountId
    {
        return new ServiceAccountId((string) new Ulid);
    }

    private function map(?ServiceAccountModel $model): ?ServiceAccount
    {
        if (! $model instanceof ServiceAccountModel) {
            return null;
        }

        /** @var list<string> $scopes */
        $scopes = array_values(array_filter($model->scopes, is_string(...)));

        return ServiceAccount::reconstitute(
            new ServiceAccountId($model->id),
            new ServiceName($model->name),
            HashedSecret::fromHash($model->secret_hash),
            ServiceAccountStatus::from($model->status),
            ScopeCollection::fromStrings($scopes),
            $this->toImmutable($model->created_at),
            $this->toImmutable($model->updated_at),
        );
    }

    private function toImmutable(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return Date::now()->toImmutable();
    }
}
