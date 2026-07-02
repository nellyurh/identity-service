<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Command\LoginCommand;
use App\Application\Auth\Result\LoginResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TokenIssuer;
use App\Application\User\AuthenticateUser;
use App\Application\User\Command\AuthenticateUserCommand;

/**
 * Login use case: verify credentials (delegated to AuthenticateUser, which owns lockout/audit
 * and no-enumeration), then mint a short-lived RS256 access token. Refresh tokens and session
 * records are added in the next slice; this returns an access token only.
 */
final readonly class LoginUser
{
    public function __construct(
        private AuthenticateUser $authenticate,
        private TokenIssuer $tokens,
        private AuditWriter $audit,
        private Clock $clock,
    ) {}

    public function handle(LoginCommand $c): LoginResult
    {
        $principal = $this->authenticate->handle(
            new AuthenticateUserCommand($c->email, $c->password, $c->requestId),
        );

        $issued = $this->tokens->issueAccessToken(
            $principal->userId,
            ['token_use' => 'access'],
            $this->clock->now(),
        );

        $this->audit->record(
            'token.issued',
            $principal->userId,
            'user:'.$principal->userId,
            [],
            ['jti' => $issued->jti, 'token_use' => 'access'],
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
        );
    }
}
