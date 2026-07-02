<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Command\LoginCommand;
use App\Application\Auth\Result\LoginResult;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Application\User\AuthenticateUser;
use App\Application\User\Command\AuthenticateUserCommand;

/**
 * Login use case: verify credentials (delegated to AuthenticateUser, which owns lockout/audit and
 * no-enumeration), then issue a session (access + refresh) via IssueUserSession, atomically.
 */
final readonly class LoginUser
{
    public function __construct(
        private AuthenticateUser $authenticate,
        private IssueUserSession $session,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(LoginCommand $c): LoginResult
    {
        $principal = $this->authenticate->handle(
            new AuthenticateUserCommand($c->email, $c->password, $c->requestId),
        );

        $now = $this->clock->now();

        return $this->tx->transactional(fn (): LoginResult => $this->session->issue(
            $principal->userId,
            $principal->status,
            $principal->emailVerified,
            $c->requestId,
            $now,
        ));
    }
}
