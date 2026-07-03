<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Identity\PasswordReset\PasswordReset;
use App\Domain\Identity\PasswordReset\Repository\PasswordResetRepository;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Model\PasswordResetModel;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Uid\Ulid;

final readonly class EloquentPasswordResetRepository implements PasswordResetRepository
{
    private const int EVENT_VERSION = 1;

    private const string SCHEMA_VERSION = '1.0.0';

    public function __construct(
        private OutboxWriter $outbox,
    ) {}

    public function save(PasswordReset $reset): void
    {
        PasswordResetModel::query()->updateOrCreate(
            ['id' => $reset->id],
            [
                'user_id' => $reset->userId->value,
                'delivery_ref' => $reset->deliveryRef,
                'token_hash' => $reset->tokenHash(),
                'expires_at' => $reset->expiresAt(),
                'materialized_at' => $reset->materializedAt(),
                'used_at' => $reset->usedAt(),
                'created_at' => $reset->createdAt(),
            ],
        );

        foreach ($reset->releaseEvents() as $event) {
            $this->outbox->write(
                $event->eventType(),
                self::EVENT_VERSION,
                self::SCHEMA_VERSION,
                'PasswordReset',
                $reset->id,
                $event->payload(),
            );
        }
    }

    public function materialize(PasswordReset $reset, string $tokenHash, DateTimeImmutable $now): bool
    {
        $won = PasswordResetModel::query()
            ->whereKey($reset->id)
            ->whereNull('materialized_at')
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->update(['token_hash' => $tokenHash, 'materialized_at' => $now]) === 1;

        if ($won) {
            $reset->materialize($tokenHash, $now); // keep the in-memory entity consistent
        }

        return $won;
    }

    public function consume(PasswordReset $reset, DateTimeImmutable $now): bool
    {
        $won = PasswordResetModel::query()
            ->whereKey($reset->id)
            ->whereNotNull('materialized_at')
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->update(['used_at' => $now]) === 1;

        if ($won) {
            $reset->consume($now); // keep the in-memory entity consistent
        }

        return $won;
    }

    public function findByDeliveryRef(string $deliveryRef): ?PasswordReset
    {
        return $this->map(PasswordResetModel::query()->where('delivery_ref', $deliveryRef)->first());
    }

    public function findByTokenHash(string $tokenHash): ?PasswordReset
    {
        return $this->map(PasswordResetModel::query()->where('token_hash', $tokenHash)->first());
    }

    public function invalidateForUser(UserId $userId): void
    {
        PasswordResetModel::query()
            ->where('user_id', $userId->value)
            ->whereNull('used_at')
            ->delete();
    }

    public function nextIdentity(): string
    {
        return (string) new Ulid;
    }

    private function map(?PasswordResetModel $model): ?PasswordReset
    {
        if (! $model instanceof PasswordResetModel) {
            return null;
        }

        return PasswordReset::reconstitute(
            $model->id,
            new UserId($model->user_id),
            $model->delivery_ref,
            $model->token_hash,
            $this->toImmutable($model->expires_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->materialized_at),
            $this->toImmutable($model->used_at),
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
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
