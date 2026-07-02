<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Command\RefreshCommand;
use App\Application\Auth\Result\RefreshResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\AuthorizationResolver;
use App\Application\Port\Clock;
use App\Application\Port\TokenBlacklist;
use App\Application\Port\TokenGenerator;
use App\Application\Port\TokenIssuer;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\Token\Exception\RefreshTokenInvalid;
use App\Domain\Identity\Token\Exception\TokenReuseDetected;
use App\Domain\Identity\Token\RefreshToken;
use App\Domain\Identity\Token\Repository\RefreshTokenRepository;
use App\Domain\Identity\Token\RevocationReason;
use DateInterval;
use DateTimeImmutable;

/**
 * Exchange a refresh token for a new access + refresh pair (rotation). The presented token is
 * looked up by hash and asserted usable; a token presented after it was already rotated is reuse —
 * the whole family is revoked, its still-valid access tokens are blacklisted, and the exchange is
 * refused. On success the current token is closed, a successor is minted in the same family, and
 * both are saved atomically with the new access token's jti so state and outbox never diverge.
 */
final readonly class RefreshTokens
{
    public function __construct(
        private TokenIssuer $tokens,
        private RefreshTokenRepository $refreshTokens,
        private TokenGenerator $tokenGenerator,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
        private TokenBlacklist $blacklist,
        private AuthorizationResolver $authorization,
        private int $refreshTtlSeconds,
        private int $accessTtlSeconds,
    ) {}

    public function handle(RefreshCommand $c): RefreshResult
    {
        $current = $this->refreshTokens->findByHash(hash('sha256', $c->refreshToken));
        if (! $current instanceof RefreshToken) {
            throw RefreshTokenInvalid::because('unknown');
        }

        $now = $this->clock->now();

        try {
            $current->assertUsable($now);
        } catch (TokenReuseDetected $e) {
            $members = $this->refreshTokens->membersOf($current->familyId());

            $this->tx->transactional(function () use ($current, $now, $c): void {
                $this->refreshTokens->revokeFamily($current->familyId(), RevocationReason::ReuseDetected, $now);
                $this->audit->record(
                    'token.reuse_detected',
                    $current->userId()->value,
                    'user:'.$current->userId()->value,
                    [],
                    ['family_id' => $current->familyId()->value],
                    $c->requestId,
                    null,
                );
            });

            $this->blacklistFamily($members, $now);

            throw $e;
        }

        return $this->tx->transactional(function () use ($current, $now, $c): RefreshResult {
            $authz = $this->authorization->resolve($current->userId());

            $issued = $this->tokens->issueAccessToken(
                $current->userId()->value,
                [
                    'token_use' => 'access',
                    'permissions' => $authz->permissions,
                    'authz_ver' => $authz->authzVersion,
                ],
                $now,
            );

            $secret = $this->tokenGenerator->generate();
            $successor = $current->rotate(
                $this->refreshTokens->nextIdentity(),
                hash('sha256', $secret),
                $issued->jti,
                $now->add(new DateInterval('PT'.$this->refreshTtlSeconds.'S')),
                $now,
            );

            $this->refreshTokens->save($current);
            $this->refreshTokens->save($successor);

            $this->audit->record(
                'token.refreshed',
                $current->userId()->value,
                'user:'.$current->userId()->value,
                [],
                ['jti' => $issued->jti, 'family_id' => $current->familyId()->value],
                $c->requestId,
                null,
            );

            return new RefreshResult(
                accessToken: $issued->token,
                tokenType: $issued->tokenType,
                expiresIn: $issued->expiresIn,
                refreshToken: $secret,
                refreshExpiresIn: $this->refreshTtlSeconds,
            );
        });
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
