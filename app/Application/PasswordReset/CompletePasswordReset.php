<?php

declare(strict_types=1);

namespace App\Application\PasswordReset;

use App\Application\PasswordReset\Command\CompletePasswordResetCommand;
use App\Application\PasswordReset\Result\CompletePasswordResetResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\PasswordHasher;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\PasswordReset\Exception\PasswordResetTokenInvalid;
use App\Domain\Identity\PasswordReset\PasswordReset;
use App\Domain\Identity\PasswordReset\Repository\PasswordResetRepository;
use App\Domain\Identity\Token\Repository\RefreshTokenRepository;
use App\Domain\Identity\Token\RevocationReason;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\ValueObject\HashedPassword;

/**
 * Complete a password reset: redeem the token, set the new password (emitting PasswordChanged), burn
 * the token, and revoke every one of the user's refresh families so all existing sessions die. No
 * current-password check — the token is the proof. Unknown/used/unmaterialised/expired tokens fail
 * generically (RESET_002).
 */
final readonly class CompletePasswordReset
{
    public function __construct(
        private PasswordResetRepository $resets,
        private UserRepository $users,
        private RefreshTokenRepository $refreshTokens,
        private PasswordHasher $hasher,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(CompletePasswordResetCommand $c): CompletePasswordResetResult
    {
        $now = $this->clock->now();
        $reset = $this->resets->findByTokenHash(hash('sha256', $c->token));

        if (! $reset instanceof PasswordReset || ! $reset->isRedeemable($now)) {
            throw PasswordResetTokenInvalid::create();
        }

        $newHash = HashedPassword::fromHash($this->hasher->hash($c->newPassword));

        return $this->tx->transactional(function () use ($c, $reset, $newHash, $now): CompletePasswordResetResult {
            $user = $this->users->getById($reset->userId);

            $user->changePassword($newHash, $now);
            $this->users->save($user);

            $reset->consume($now);
            $this->resets->save($reset);

            $this->refreshTokens->revokeAllForUser($user->id, RevocationReason::PasswordChange, $now);

            $this->audit->record('password.reset', $user->id->value, 'user:'.$user->id->value, [], [], $c->requestId, null);

            return new CompletePasswordResetResult($user->id->value, true);
        });
    }
}
