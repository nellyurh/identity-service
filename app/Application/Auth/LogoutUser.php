<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Command\LogoutCommand;
use App\Application\Auth\Result\LogoutResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\Token\Exception\RefreshTokenInvalid;
use App\Domain\Identity\Token\RefreshToken;
use App\Domain\Identity\Token\Repository\RefreshTokenRepository;
use App\Domain\Identity\Token\RevocationReason;

/**
 * Log out: revoke the entire refresh-token family the presented token belongs to, so no member
 * (including tokens on other devices from the same login lineage) can be exchanged again. The
 * short-lived access token is not blacklisted here — it self-expires within the access TTL;
 * active revocation of outstanding access tokens arrives with introspection (next slice).
 * Idempotent: logging out an already-revoked family is a no-op that still returns success.
 */
final readonly class LogoutUser
{
    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(LogoutCommand $c): LogoutResult
    {
        $current = $this->refreshTokens->findByHash(hash('sha256', $c->refreshToken));
        if (! $current instanceof RefreshToken) {
            throw RefreshTokenInvalid::because('unknown');
        }

        $now = $this->clock->now();

        return $this->tx->transactional(function () use ($current, $now, $c): LogoutResult {
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
    }
}
