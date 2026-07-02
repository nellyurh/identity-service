<?php

declare(strict_types=1);

namespace App\Application\PasswordReset;

use App\Application\PasswordReset\Command\RequestPasswordResetCommand;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TokenGenerator;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\PasswordReset\PasswordReset;
use App\Domain\Identity\PasswordReset\Repository\PasswordResetRepository;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;
use DateInterval;

/**
 * Begin a password reset. Always succeeds from the caller's perspective (the endpoint returns 202
 * regardless) so the presence of an account is never revealed. When the email matches a user, any
 * outstanding reset is superseded and a new one is created with an opaque delivery_ref; the
 * PasswordResetRequested event carries only that ref — no email, no token. The token itself is minted
 * later, at materialisation.
 */
final readonly class RequestPasswordReset
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetRepository $resets,
        private TokenGenerator $generator,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
        private int $ttlSeconds,
    ) {}

    public function handle(RequestPasswordResetCommand $c): void
    {
        $user = $this->users->findByEmail(new Email($c->email));
        if (! $user instanceof User) {
            return; // no enumeration: identical outcome whether or not the account exists
        }

        $this->tx->transactional(function () use ($c, $user): void {
            $now = $this->clock->now();
            $expiresAt = $now->add(new DateInterval('PT'.$this->ttlSeconds.'S'));

            $this->resets->invalidateForUser($user->id);
            $this->resets->save(PasswordReset::create(
                $this->resets->nextIdentity(),
                $user->id,
                $this->generator->generate(),
                $expiresAt,
                $now,
            ));

            $this->audit->record('password.reset_requested', $user->id->value, 'user:'.$user->id->value, [], [], $c->requestId, null);
        });
    }
}
