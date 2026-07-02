<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Identity\Mfa\MfaChallenge;
use App\Domain\Identity\Mfa\Repository\MfaChallengeRepository;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Model\MfaChallengeModel;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Uid\Ulid;

final readonly class EloquentMfaChallengeRepository implements MfaChallengeRepository
{
    public function save(MfaChallenge $challenge): void
    {
        MfaChallengeModel::query()->updateOrCreate(
            ['id' => $challenge->id],
            [
                'user_id' => $challenge->userId->value,
                'token_hash' => $challenge->tokenHash,
                'expires_at' => $challenge->expiresAt(),
                'used_at' => $challenge->usedAt(),
                'created_at' => $challenge->createdAt(),
            ],
        );
    }

    public function findByHash(string $tokenHash): ?MfaChallenge
    {
        $model = MfaChallengeModel::query()->where('token_hash', $tokenHash)->first();
        if (! $model instanceof MfaChallengeModel) {
            return null;
        }

        return MfaChallenge::reconstitute(
            $model->id,
            new UserId($model->user_id),
            $model->token_hash,
            $this->toImmutable($model->expires_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->used_at),
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
        );
    }

    public function invalidateForUser(UserId $userId): void
    {
        MfaChallengeModel::query()->where('user_id', $userId->value)->whereNull('used_at')->delete();
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
