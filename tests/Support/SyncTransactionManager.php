<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Port\TransactionManager;

/** Runs the unit of work inline — application-service tests need no real database. */
final class SyncTransactionManager implements TransactionManager
{
    public function transactional(callable $work): mixed
    {
        return $work();
    }
}
