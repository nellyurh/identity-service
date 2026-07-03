<?php

declare(strict_types=1);

namespace App\Application\PasswordReset;

use App\Application\PasswordReset\Command\MaterializePasswordResetDeliveryCommand;
use App\Application\PasswordReset\Result\MaterializedResetResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TokenGenerator;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\PasswordReset\Exception\PasswordResetDeliveryInvalid;
use App\Domain\Identity\PasswordReset\PasswordReset;
use App\Domain\Identity\PasswordReset\Repository\PasswordResetRepository;
use App\Domain\Identity\User\Repository\UserRepository;

/**
 * Exchange a delivery_ref (from the PasswordResetRequested event) for a freshly-minted token plus the
 * recipient email, so the authenticated notification service can send the reset link. The token is
 * generated here — not at request time — and only its hash is stored; the raw value is returned once
 * and never again. A delivery_ref can be materialised only once.
 */
final readonly class MaterializePasswordResetDelivery
{
    public function __construct(
        private PasswordResetRepository $resets,
        private UserRepository $users,
        private TokenGenerator $generator,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(MaterializePasswordResetDeliveryCommand $c): MaterializedResetResult
    {
        $now = $this->clock->now();
        $reset = $this->resets->findByDeliveryRef($c->deliveryRef);

        if (! $reset instanceof PasswordReset || ! $reset->isDeliverable($now)) {
            throw PasswordResetDeliveryInvalid::create();
        }

        return $this->tx->transactional(function () use ($c, $reset, $now): MaterializedResetResult {
            $raw = $this->generator->generate();

            // Atomic: exactly one caller can bind a token to this delivery_ref. Without this, two
            // concurrent materialisations would silently clobber each other's hash — the first
            // responder would be emailing a token that no longer matches the row.
            if (! $this->resets->materialize($reset, hash('sha256', $raw), $now)) {
                throw PasswordResetDeliveryInvalid::create();
            }

            $user = $this->users->getById($reset->userId);

            $this->audit->record('password.reset_materialized', $c->actorId, 'user:'.$reset->userId->value, [], [], $c->requestId, null);

            return new MaterializedResetResult($user->email()->value, $raw, $reset->expiresAt()->format(DATE_RFC3339));
        });
    }
}
