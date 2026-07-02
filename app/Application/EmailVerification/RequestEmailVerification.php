<?php

declare(strict_types=1);

namespace App\Application\EmailVerification;

use App\Application\EmailVerification\Command\RequestEmailVerificationCommand;
use App\Application\EmailVerification\Result\RequestEmailVerificationResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TokenGenerator;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\EmailVerification\EmailVerificationToken;
use App\Domain\Identity\EmailVerification\Repository\EmailVerificationTokenRepository;
use App\Domain\Identity\User\Exception\EmailAlreadyVerified;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\ValueObject\UserId;
use DateInterval;

/**
 * Issue a single-use email verification token for a user. Stores only the token's hash and returns
 * the raw token once to the trusted caller for delivery by email — the raw value never enters an
 * event or the outbox. Requesting again invalidates any outstanding token. Already-verified users
 * are rejected (USER_003). No domain event is emitted at request time (only on verify).
 */
final readonly class RequestEmailVerification
{
    public function __construct(
        private UserRepository $users,
        private EmailVerificationTokenRepository $tokens,
        private TokenGenerator $generator,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
        private int $ttlSeconds,
    ) {}

    public function handle(RequestEmailVerificationCommand $c): RequestEmailVerificationResult
    {
        $user = $this->users->getById(new UserId($c->userId));

        if ($user->isEmailVerified()) {
            throw EmailAlreadyVerified::create();
        }

        return $this->tx->transactional(function () use ($c, $user): RequestEmailVerificationResult {
            $now = $this->clock->now();
            $raw = $this->generator->generate();
            $expiresAt = $now->add(new DateInterval('PT'.$this->ttlSeconds.'S'));

            $this->tokens->invalidateForUser($user->id);
            $this->tokens->save(EmailVerificationToken::create(
                $this->tokens->nextIdentity(),
                $user->id,
                hash('sha256', $raw),
                $expiresAt,
                $now,
            ));

            $this->audit->record('email.verification_requested', $c->actorId, 'user:'.$user->id->value, [], [], $c->requestId, null);

            return new RequestEmailVerificationResult($raw, $expiresAt->format(DATE_RFC3339));
        });
    }
}
