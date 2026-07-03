<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Identity\Mfa\RecoveryCode;
use App\Domain\Identity\Mfa\Repository\RecoveryCodeRepository;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Model\RecoveryCodeModel;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Uid\Ulid;

final readonly class EloquentRecoveryCodeRepository implements RecoveryCodeRepository
{
    /** @param list<RecoveryCode> $codes */
    public function saveMany(array $codes): void
    {
        foreach ($codes as $code) {
            $this->save($code);
        }
    }

    public function save(RecoveryCode $code): void
    {
        RecoveryCodeModel::query()->updateOrCreate(
            ['id' => $code->id],
            [
                'user_id' => $code->userId->value,
                'code_hash' => $code->codeHash,
                'used_at' => $code->usedAt(),
                'created_at' => $code->createdAt,
            ],
        );
    }

    public function consume(RecoveryCode $code, DateTimeImmutable $now): bool
    {
        $won = RecoveryCodeModel::query()
            ->whereKey($code->id)
            ->whereNull('used_at')
            ->update(['used_at' => $now]) === 1;

        if ($won) {
            $code->consume($now); // keep the in-memory entity consistent
        }

        return $won;
    }

    public function findByHashForUser(UserId $userId, string $codeHash): ?RecoveryCode
    {
        $model = RecoveryCodeModel::query()
            ->where('user_id', $userId->value)
            ->where('code_hash', $codeHash)
            ->first();

        if (! $model instanceof RecoveryCodeModel) {
            return null;
        }

        return RecoveryCode::reconstitute(
            $model->id,
            new UserId($model->user_id),
            $model->code_hash,
            $this->toImmutable($model->used_at),
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
        );
    }

    public function countUsableForUser(UserId $userId): int
    {
        return RecoveryCodeModel::query()
            ->where('user_id', $userId->value)
            ->whereNull('used_at')
            ->count();
    }

    public function deleteForUser(UserId $userId): void
    {
        RecoveryCodeModel::query()->where('user_id', $userId->value)->delete();
    }

    public function nextIdentity(): string
    {
        return (string) new Ulid;
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
