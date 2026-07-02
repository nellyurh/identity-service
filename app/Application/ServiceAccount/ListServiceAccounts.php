<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount;

use App\Application\ServiceAccount\Result\ServiceAccountView;
use App\Domain\Identity\ServiceAccount\Repository\ServiceAccountRepository;

final readonly class ListServiceAccounts
{
    public function __construct(
        private ServiceAccountRepository $accounts,
    ) {}

    /** @return list<ServiceAccountView> */
    public function handle(): array
    {
        return array_map(
            ServiceAccountView::fromAccount(...),
            $this->accounts->all(),
        );
    }
}
