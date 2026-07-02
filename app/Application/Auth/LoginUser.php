<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Command\LoginCommand;
use App\Application\Auth\Result\LoginOutcome;
use App\Application\Auth\Result\MfaChallengeIssued;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TokenGenerator;
use App\Application\Port\TransactionManager;
use App\Application\User\AuthenticateUser;
use App\Application\User\Command\AuthenticateUserCommand;
use App\Domain\Identity\Mfa\MfaChallenge;
use App\Domain\Identity\Mfa\Repository\MfaChallengeRepository;
use App\Domain\Identity\Mfa\Repository\TotpCredentialRepository;
use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\User\ValueObject\UserId;
use DateInterval;

/**
 * Login use case: verify credentials (delegated to AuthenticateUser). If the user has an active
 * second factor, issue a short-lived opaque MFA challenge instead of tokens; the caller completes it
 * with a code. Otherwise issue a session directly via IssueUserSession. Both branches are atomic.
 */
final readonly class LoginUser
{
    public function __construct(
        private AuthenticateUser $authenticate,
        private IssueUserSession $session,
        private TotpCredentialRepository $totpCredentials,
        private MfaChallengeRepository $challenges,
        private TokenGenerator $tokenGenerator,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
        private int $challengeTtlSeconds,
    ) {}

    public function handle(LoginCommand $c): LoginOutcome
    {
        $principal = $this->authenticate->handle(
            new AuthenticateUserCommand($c->email, $c->password, $c->requestId),
        );

        $now = $this->clock->now();
        $userId = UserId::fromString($principal->userId);

        if ($this->totpCredentials->findActiveForUser($userId) instanceof TotpCredential) {
            return $this->tx->transactional(function () use ($c, $userId, $principal, $now): LoginOutcome {
                $secret = $this->tokenGenerator->generate();

                $this->challenges->invalidateForUser($userId);
                $this->challenges->save(MfaChallenge::create(
                    $this->challenges->nextIdentity(),
                    $userId,
                    hash('sha256', $secret),
                    $now->add(new DateInterval('PT'.$this->challengeTtlSeconds.'S')),
                    $now,
                ));

                $this->audit->record('login.mfa_challenged', $principal->userId, 'user:'.$principal->userId, [], [], $c->requestId, null);

                return LoginOutcome::mfaRequired(new MfaChallengeIssued($secret, $this->challengeTtlSeconds, $principal->userId));
            });
        }

        return $this->tx->transactional(fn (): LoginOutcome => LoginOutcome::authenticated($this->session->issue(
            $principal->userId,
            $principal->status,
            $principal->emailVerified,
            $c->requestId,
            $now,
        )));
    }
}
