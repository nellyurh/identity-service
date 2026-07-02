<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\Repository;

use App\Domain\Identity\ServiceAccount\Exception\ServiceAccountNotFound;
use App\Domain\Identity\ServiceAccount\ServiceAccount;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceAccountId;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceName;

interface ServiceAccountRepository
{
    public function findById(ServiceAccountId $id): ?ServiceAccount;

    public function findByName(ServiceName $name): ?ServiceAccount;

    /** @throws ServiceAccountNotFound */
    public function getById(ServiceAccountId $id): ServiceAccount;

    public function existsByName(ServiceName $name): bool;

    /** @return list<ServiceAccount> */
    public function all(): array;

    /** Persist the aggregate and drain its recorded events to the outbox atomically. */
    public function save(ServiceAccount $account): void;

    public function nextIdentity(): ServiceAccountId;
}
