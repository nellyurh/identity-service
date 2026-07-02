<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Command\LoginCommand;
use App\Application\Auth\Result\LoginResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\AuthorizationResolver;
use App\Application\Port\Clock;
use App\Application\Port\TokenGenerator;
use App\Application\Port\TokenIssuer;
use App\Application\Port\TransactionManager;
use App\Application\User\AuthenticateUser;
use App\Application\User\Command\AuthenticateUserCommand;
use App\Domain\Identity\Token\RefreshToken;
use App\Domain\Identity\Token\Repository\RefreshTokenRepository;
use App\Domain\Identity\User\ValueObject\UserId;
use DateInterval;

/**
 * Login use case: verify credentials (delegated to AuthenticateUser, which owns lockout/audit
 * and no-enumeration), then mint a short-lived RS256 access token AND a rotating refresh token
 * in a new family. The opaque refresh secret is returned to the client exactly once; only its
 * SHA-256 hash is persisted. Access issuance + refresh persistence + audit happen atomically so
 * the TokenIssued outbox row can never diverge from the stored token.
 */
final readonly class LoginUser
{
    public function __construct(
        private AuthenticateUser $authenticate,
        private TokenIssuer $tokens,
        private RefreshTokenRepository $refreshTokens,
        private TokenGenerator $tokenGenerator,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
        private AuthorizationResolver $authorization,
        private int $refreshTtlSeconds,
    ) {}

    public function handle(LoginCommand $c): LoginResult
    {
        $principal = $this->authenticate->handle(
            new AuthenticateUserCommand($c->email, $c->password, $c->requestId),
        );

        $now = $this->clock->now();

        return $this->tx->transactional(function () use ($c, $principal, $now): LoginResult {
            $authz = $this->authorization->resolve(UserId::fromString($principal->userId));

            $issued = $this->tokens->issueAccessToken(
                $principal->userId,
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
                UserId::fromString($principal->userId),
                $family,
                hash('sha256', $secret),
                $issued->jti,
                $now->add(new DateInterval('PT'.$this->refreshTtlSeconds.'S')),
                $now,
            );
            $this->refreshTokens->save($token);

            $this->audit->record(
                'token.issued',
                $principal->userId,
                'user:'.$principal->userId,
                [],
                ['jti' => $issued->jti, 'family_id' => $family->value, 'token_use' => 'access'],
                $c->requestId,
                null,
            );

            return new LoginResult(
                userId: $principal->userId,
                status: $principal->status,
                emailVerified: $principal->emailVerified,
                accessToken: $issued->token,
                tokenType: $issued->tokenType,
                expiresIn: $issued->expiresIn,
                refreshToken: $secret,
                refreshExpiresIn: $this->refreshTtlSeconds,
            );
        });
    }
}
