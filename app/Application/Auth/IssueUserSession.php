<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Result\LoginResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\AuthorizationResolver;
use App\Application\Port\TokenGenerator;
use App\Application\Port\TokenIssuer;
use App\Domain\Identity\Token\RefreshToken;
use App\Domain\Identity\Token\Repository\RefreshTokenRepository;
use App\Domain\Identity\User\ValueObject\UserId;
use DateInterval;
use DateTimeImmutable;

/**
 * Mint a session for an already-authenticated user: a short-lived RS256 access token carrying the
 * user's permissions + authz_version, plus a rotating refresh token in a fresh family. The opaque
 * refresh secret is returned once; only its hash is stored. Shared by the direct login path and the
 * MFA-completion path. Must run inside the caller's transaction so the TokenIssued outbox row can
 * never diverge from the persisted refresh token.
 */
final readonly class IssueUserSession
{
    public function __construct(
        private TokenIssuer $tokens,
        private RefreshTokenRepository $refreshTokens,
        private TokenGenerator $tokenGenerator,
        private AuditWriter $audit,
        private AuthorizationResolver $authorization,
        private int $refreshTtlSeconds,
    ) {}

    public function issue(string $userId, string $status, bool $emailVerified, string $requestId, DateTimeImmutable $now): LoginResult
    {
        $authz = $this->authorization->resolve(UserId::fromString($userId));

        $issued = $this->tokens->issueAccessToken(
            $userId,
            [
                'token_use' => 'access',
                'permissions' => $authz->permissions,
                'authz_ver' => $authz->authzVersion,
            ],
            $now,
        );

        $secret = $this->tokenGenerator->generate();
        $family = $this->refreshTokens->nextFamilyIdentity();

        $token = RefreshToken::issue(
            $this->refreshTokens->nextIdentity(),
            UserId::fromString($userId),
            $family,
            hash('sha256', $secret),
            $issued->jti,
            $now->add(new DateInterval('PT'.$this->refreshTtlSeconds.'S')),
            $now,
        );
        $this->refreshTokens->save($token);

        $this->audit->record(
            'token.issued',
            $userId,
            'user:'.$userId,
            [],
            ['jti' => $issued->jti, 'family_id' => $family->value, 'token_use' => 'access'],
            $requestId,
            null,
        );

        return new LoginResult(
            userId: $userId,
            status: $status,
            emailVerified: $emailVerified,
            accessToken: $issued->token,
            tokenType: $issued->tokenType,
            expiresIn: $issued->expiresIn,
            refreshToken: $secret,
            refreshExpiresIn: $this->refreshTtlSeconds,
        );
    }
}
