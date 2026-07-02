<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TokenGenerator;
use App\Application\Port\TransactionManager;
use App\Application\ServiceAccount\Command\CreateServiceAccountCommand;
use App\Application\ServiceAccount\Result\ServiceAccountCredential;
use App\Application\ServiceAccount\Result\ServiceAccountView;
use App\Domain\Identity\ServiceAccount\Exception\ServiceNameTaken;
use App\Domain\Identity\ServiceAccount\Repository\ServiceAccountRepository;
use App\Domain\Identity\ServiceAccount\ServiceAccount;
use App\Domain\Identity\ServiceAccount\ValueObject\HashedSecret;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceName;

/**
 * Provision a new service account. Generates a high-entropy client secret, stores only its
 * SHA-256 hash, and returns the plaintext exactly once. The service name is unique and doubles as
 * the client_id at the token endpoint.
 */
final readonly class CreateServiceAccount
{
    public function __construct(
        private ServiceAccountRepository $accounts,
        private TokenGenerator $secrets,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(CreateServiceAccountCommand $c): ServiceAccountCredential
    {
        $name = new ServiceName($c->name);
        if ($this->accounts->existsByName($name)) {
            throw ServiceNameTaken::withName($name->value);
        }

        $scopes = ScopeCollection::fromStrings($c->scopes);

        return $this->tx->transactional(function () use ($c, $name, $scopes): ServiceAccountCredential {
            $secret = $this->secrets->generate();

            $account = ServiceAccount::create(
                $this->accounts->nextIdentity(),
                $name,
                HashedSecret::fromHash(hash('sha256', $secret)),
                $scopes,
                $this->clock->now(),
            );
            $this->accounts->save($account);

            $this->audit->record(
                'service_account.created',
                $c->actorId,
                'service_account:'.$account->id->value,
                [],
                ['name' => $name->value, 'scopes' => $scopes->toArray()],
                $c->requestId,
                null,
            );

            return new ServiceAccountCredential(ServiceAccountView::fromAccount($account), $secret);
        });
    }
}
