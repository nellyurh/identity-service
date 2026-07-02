<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Application\ServiceAccount\Command\DisableServiceAccountCommand;
use App\Application\ServiceAccount\Result\ServiceAccountView;
use App\Domain\Identity\ServiceAccount\Repository\ServiceAccountRepository;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceAccountId;

/** Disable a service account (idempotent). A disabled account can no longer obtain service tokens. */
final readonly class DisableServiceAccount
{
    public function __construct(
        private ServiceAccountRepository $accounts,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(DisableServiceAccountCommand $c): ServiceAccountView
    {
        $account = $this->accounts->getById(new ServiceAccountId($c->serviceAccountId));

        return $this->tx->transactional(function () use ($c, $account): ServiceAccountView {
            $account->disable($this->clock->now());
            $this->accounts->save($account);

            $this->audit->record(
                'service_account.disabled',
                $c->actorId,
                'service_account:'.$account->id->value,
                [],
                [],
                $c->requestId,
                null,
            );

            return ServiceAccountView::fromAccount($account);
        });
    }
}
