<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TokenGenerator;
use App\Application\Port\TransactionManager;
use App\Application\ServiceAccount\Command\RotateServiceAccountCredentialCommand;
use App\Application\ServiceAccount\Result\ServiceAccountCredential;
use App\Application\ServiceAccount\Result\ServiceAccountView;
use App\Domain\Identity\ServiceAccount\Repository\ServiceAccountRepository;
use App\Domain\Identity\ServiceAccount\ValueObject\HashedSecret;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceAccountId;

/**
 * Rotate a service account's client secret. Issues a fresh secret (returned once), replaces the
 * stored hash, and emits ServiceAccountCredentialRotated. The old secret stops working immediately.
 */
final readonly class RotateServiceAccountCredential
{
    public function __construct(
        private ServiceAccountRepository $accounts,
        private TokenGenerator $secrets,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(RotateServiceAccountCredentialCommand $c): ServiceAccountCredential
    {
        $account = $this->accounts->getById(new ServiceAccountId($c->serviceAccountId));

        return $this->tx->transactional(function () use ($c, $account): ServiceAccountCredential {
            $secret = $this->secrets->generate();
            $account->rotateSecret(HashedSecret::fromHash(hash('sha256', $secret)), $this->clock->now());
            $this->accounts->save($account);

            $this->audit->record(
                'service_account.credential_rotated',
                $c->actorId,
                'service_account:'.$account->id->value,
                [],
                [],
                $c->requestId,
                null,
            );

            return new ServiceAccountCredential(ServiceAccountView::fromAccount($account), $secret);
        });
    }
}
