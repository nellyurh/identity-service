<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount;

use App\Application\ServiceAccount\Result\ServiceAccountView;
use App\Domain\Identity\ServiceAccount\Repository\ServiceAccountRepository;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceAccountId;

final readonly class GetServiceAccount
{
    public function __construct(
        private ServiceAccountRepository $accounts,
    ) {}

    public function handle(string $id): ServiceAccountView
    {
        return ServiceAccountView::fromAccount($this->accounts->getById(new ServiceAccountId($id)));
    }
}
