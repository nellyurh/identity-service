<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Identity\EmailVerification\EmailVerificationToken;
use App\Domain\Identity\EmailVerification\Repository\EmailVerificationTokenRepository;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Model\EmailVerificationTokenModel;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Uid\Ulid;

final readonly class EloquentEmailVerificationTokenRepository implements EmailVerificationTokenRepository
{
    public function save(EmailVerificationToken $token): void
    {
        EmailVerificationTokenModel::query()->updateOrCreate(
            ['id' => $token->id],
            [
                'user_id' => $token->userId->value,
                'token_hash' => $token->tokenHash,
                'expires_at' => $token->expiresAt(),
                'used_at' => $token->usedAt(),
                'created_at' => $token->createdAt(),
            ],
        );
    }

    public function findByHash(string $tokenHash): ?EmailVerificationToken
    {
        $model = EmailVerificationTokenModel::query()->where('token_hash', $tokenHash)->first();
        if (! $model instanceof EmailVerificationTokenModel) {
            return null;
        }

        return EmailVerificationToken::reconstitute(
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
        EmailVerificationTokenModel::query()
            ->where('user_id', $userId->value)
            ->whereNull('used_at')
            ->delete();
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
