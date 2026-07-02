<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Identity\Mfa\Repository\TotpCredentialRepository;
use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\Mfa\TotpStatus;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Model\TotpCredentialModel;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Uid\Ulid;

final readonly class EloquentTotpCredentialRepository implements TotpCredentialRepository
{
    private const int EVENT_VERSION = 1;

    private const string SCHEMA_VERSION = '1.0.0';

    public function __construct(
        private OutboxWriter $outbox,
    ) {}

    public function save(TotpCredential $credential): void
    {
        TotpCredentialModel::query()->updateOrCreate(
            ['id' => $credential->id],
            [
                'user_id' => $credential->userId->value,
                'secret' => $credential->encryptedSecret(),
                'status' => $credential->status()->value,
                'confirmed_at' => $credential->confirmedAt(),
                'created_at' => $credential->createdAt(),
                'updated_at' => $credential->updatedAt(),
            ],
        );

        foreach ($credential->releaseEvents() as $event) {
            $this->outbox->write(
                $event->eventType(),
                self::EVENT_VERSION,
                self::SCHEMA_VERSION,
                'TotpCredential',
                $credential->id,
                $event->payload(),
            );
        }
    }

    public function findActiveForUser(UserId $userId): ?TotpCredential
    {
        return $this->map(TotpCredentialModel::query()
            ->where('user_id', $userId->value)
            ->where('status', TotpStatus::Active->value)
            ->first());
    }

    public function findPendingForUser(UserId $userId): ?TotpCredential
    {
        return $this->map(TotpCredentialModel::query()
            ->where('user_id', $userId->value)
            ->where('status', TotpStatus::Pending->value)
            ->first());
    }

    public function deleteForUser(UserId $userId): void
    {
        TotpCredentialModel::query()->where('user_id', $userId->value)->delete();
    }

    public function nextIdentity(): string
    {
        return (string) new Ulid;
    }

    private function map(?TotpCredentialModel $model): ?TotpCredential
    {
        if (! $model instanceof TotpCredentialModel) {
            return null;
        }

        return TotpCredential::reconstitute(
            $model->id,
            new UserId($model->user_id),
            $model->secret,
            TotpStatus::from($model->status),
            $this->toImmutable($model->confirmed_at),
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
