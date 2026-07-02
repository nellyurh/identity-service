<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Identity\Token\Event\TokenRevoked;
use App\Domain\Identity\Token\RefreshToken;
use App\Domain\Identity\Token\Repository\RefreshTokenRepository;
use App\Domain\Identity\Token\RevocationReason;
use App\Domain\Identity\Token\ValueObject\FamilyId;
use App\Domain\Identity\Token\ValueObject\RefreshTokenId;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Model\RefreshTokenModel;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Eloquent adapter for RefreshTokenRepository. save() upserts one token and drains its recorded
 * events into the outbox in the caller's transaction. revokeFamily() closes a whole lineage in a
 * single UPDATE and emits ONE family-level TokenRevoked (synthesised here, not from an aggregate,
 * because the operation spans many rows) — idempotent: if nothing was still active, no event.
 */
final readonly class EloquentRefreshTokenRepository implements RefreshTokenRepository
{
    private const int EVENT_VERSION = 1;

    private const string SCHEMA_VERSION = '1.0.0';

    public function __construct(private OutboxWriter $outbox) {}

    public function findByHash(string $tokenHash): ?RefreshToken
    {
        return $this->map(RefreshTokenModel::query()->where('token_hash', $tokenHash)->first());
    }

    /** @return list<RefreshToken> */
    public function membersOf(FamilyId $familyId): array
    {
        return array_values(
            RefreshTokenModel::query()
                ->where('family_id', $familyId->value)
                ->get()
                ->map(fn (RefreshTokenModel $model): RefreshToken => $this->hydrate($model))
                ->all(),
        );
    }

    public function save(RefreshToken $token): void
    {
        RefreshTokenModel::query()->updateOrCreate(
            ['id' => $token->id->value],
            [
                'user_id' => $token->userId()->value,
                'family_id' => $token->familyId()->value,
                'token_hash' => $token->tokenHash(),
                'access_jti' => $token->accessJti(),
                'expires_at' => $token->expiresAt(),
                'created_at' => $token->createdAt(),
                'rotated_at' => $token->rotatedAt(),
                'replaced_by' => $token->replacedBy()?->value,
                'revoked_at' => $token->revokedAt(),
            ],
        );

        foreach ($token->releaseEvents() as $event) {
            $this->outbox->write(
                $event->eventType(),
                self::EVENT_VERSION,
                self::SCHEMA_VERSION,
                'RefreshToken',
                $token->id->value,
                $event->payload(),
            );
        }
    }

    public function revokeFamily(FamilyId $familyId, RevocationReason $reason, DateTimeImmutable $now): void
    {
        $any = RefreshTokenModel::query()->where('family_id', $familyId->value)->first();
        if (! $any instanceof RefreshTokenModel) {
            return;
        }

        $affected = RefreshTokenModel::query()
            ->where('family_id', $familyId->value)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);

        if ($affected === 0) {
            return;
        }

        $event = new TokenRevoked(
            userId: $any->user_id,
            familyId: $familyId->value,
            reason: $reason->value,
            occurredAt: $now->format(DATE_RFC3339),
        );

        $this->outbox->write(
            $event->eventType(),
            self::EVENT_VERSION,
            self::SCHEMA_VERSION,
            'RefreshToken',
            $familyId->value,
            $event->payload(),
        );
    }

    public function revokeAllForUser(UserId $userId, RevocationReason $reason, DateTimeImmutable $now): void
    {
        $familyIds = RefreshTokenModel::query()
            ->where('user_id', $userId->value)
            ->whereNull('revoked_at')
            ->distinct()
            ->pluck('family_id');

        if ($familyIds->isEmpty()) {
            return;
        }

        RefreshTokenModel::query()
            ->where('user_id', $userId->value)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);

        foreach ($familyIds as $familyId) {
            $event = new TokenRevoked(
                userId: $userId->value,
                familyId: (string) $familyId,
                reason: $reason->value,
                occurredAt: $now->format(DATE_RFC3339),
            );

            $this->outbox->write(
                $event->eventType(),
                self::EVENT_VERSION,
                self::SCHEMA_VERSION,
                'RefreshToken',
                (string) $familyId,
                $event->payload(),
            );
        }
    }

    public function nextIdentity(): RefreshTokenId
    {
        return new RefreshTokenId((string) new Ulid);
    }

    public function nextFamilyIdentity(): FamilyId
    {
        return new FamilyId((string) new Ulid);
    }

    private function map(?RefreshTokenModel $model): ?RefreshToken
    {
        if (! $model instanceof RefreshTokenModel) {
            return null;
        }

        return $this->hydrate($model);
    }

    private function hydrate(RefreshTokenModel $model): RefreshToken
    {
        return RefreshToken::reconstitute(
            new RefreshTokenId($model->id),
            UserId::fromString($model->user_id),
            new FamilyId($model->family_id),
            $model->token_hash,
            $model->access_jti,
            DateTimeImmutable::createFromInterface($model->expires_at),
            DateTimeImmutable::createFromInterface($model->created_at),
            $this->toImmutable($model->rotated_at),
            $model->replaced_by !== null ? new RefreshTokenId($model->replaced_by) : null,
            $this->toImmutable($model->revoked_at),
        );
    }

    private function toImmutable(?DateTimeInterface $value): ?DateTimeImmutable
    {
        if (! $value instanceof DateTimeInterface) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($value);
    }
}
