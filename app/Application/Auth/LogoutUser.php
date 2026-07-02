<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Command\LogoutCommand;
use App\Application\Auth\Result\LogoutResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TokenBlacklist;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\Token\Exception\RefreshTokenInvalid;
use App\Domain\Identity\Token\RefreshToken;
use App\Domain\Identity\Token\Repository\RefreshTokenRepository;
use App\Domain\Identity\Token\RevocationReason;
use DateInterval;
use DateTimeImmutable;

/**
 * Log out: revoke the entire refresh-token family the presented token belongs to, and blacklist
 * every still-valid access jti in that family so outstanding access tokens die immediately rather
 * than lingering until their TTL. The DB revocation is transactional; blacklist writes happen only
 * after that commit succeeds (a rolled-back logout must not leave stray denylist entries).
 * Idempotent: logging out an already-revoked family is a no-op that still returns success.
 */
final readonly class LogoutUser
{
    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
        private TokenBlacklist $blacklist,
        private int $accessTtlSeconds,
    ) {}

    public function handle(LogoutCommand $c): LogoutResult
    {
        $current = $this->refreshTokens->findByHash(hash('sha256', $c->refreshToken));
        if (! $current instanceof RefreshToken) {
            throw RefreshTokenInvalid::because('unknown');
        }

        $now = $this->clock->now();
        $members = $this->refreshTokens->membersOf($current->familyId());

        $result = $this->tx->transactional(function () use ($current, $now, $c): LogoutResult {
            $this->refreshTokens->revokeFamily($current->familyId(), RevocationReason::Logout, $now);

            $this->audit->record(
                'logout',
                $current->userId()->value,
                'user:'.$current->userId()->value,
                [],
                ['family_id' => $current->familyId()->value],
                $c->requestId,
                null,
            );

            return new LogoutResult(
                userId: $current->userId()->value,
                familyId: $current->familyId()->value,
            );
        });

        $this->blacklistFamily($members, $now);

        return $result;
    }

    /**
     * @param  list<RefreshToken>  $members
     */
    private function blacklistFamily(array $members, DateTimeImmutable $now): void
    {
        foreach ($members as $member) {
            $accessExpiry = $member->createdAt()->add(new DateInterval('PT'.$this->accessTtlSeconds.'S'));
            if ($accessExpiry > $now) {
                $this->blacklist->blacklist($member->accessJti(), $accessExpiry);
            }
        }
    }
}
