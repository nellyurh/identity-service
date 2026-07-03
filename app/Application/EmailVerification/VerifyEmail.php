<?php

declare(strict_types=1);

namespace App\Application\EmailVerification;

use App\Application\EmailVerification\Command\VerifyEmailCommand;
use App\Application\EmailVerification\Result\VerifyEmailResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\EmailVerification\EmailVerificationToken;
use App\Domain\Identity\EmailVerification\Exception\EmailVerificationTokenInvalid;
use App\Domain\Identity\EmailVerification\Repository\EmailVerificationTokenRepository;
use App\Domain\Identity\User\Repository\UserRepository;

/**
 * Consume a verification token: mark the user's email verified (emitting EmailVerified) and burn the
 * token. Unknown/used/expired tokens fail generically (VERIFICATION_001). Idempotent for an
 * already-verified user — the token is still consumed but no second event is emitted.
 */
final readonly class VerifyEmail
{
    public function __construct(
        private EmailVerificationTokenRepository $tokens,
        private UserRepository $users,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(VerifyEmailCommand $c): VerifyEmailResult
    {
        $now = $this->clock->now();
        $token = $this->tokens->findByHash(hash('sha256', $c->token));

        if (! $token instanceof EmailVerificationToken || ! $token->isUsable($now)) {
            throw EmailVerificationTokenInvalid::create();
        }

        return $this->tx->transactional(function () use ($c, $token, $now): VerifyEmailResult {
            // Atomic single-use: win the token before mutating anything. A concurrent request that
            // already consumed it (or an expiry racing the earlier check) aborts here.
            if (! $this->tokens->consume($token, $now)) {
                throw EmailVerificationTokenInvalid::create();
            }

            $user = $this->users->getById($token->userId);

            if (! $user->isEmailVerified()) {
                $user->verifyEmail($now);
                $this->users->save($user);
            }

            $this->audit->record('email.verified', $user->id->value, 'user:'.$user->id->value, [], [], $c->requestId, null);

            return new VerifyEmailResult($user->id->value, true);
        });
    }
}
