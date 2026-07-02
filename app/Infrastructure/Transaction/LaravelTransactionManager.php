<?php

declare(strict_types=1);

namespace App\Infrastructure\Transaction;

use App\Application\Port\TransactionManager;
use Illuminate\Support\Facades\DB;

/** Runs the unit of work inside a real database transaction (Ports & Adapters). */
final class LaravelTransactionManager implements TransactionManager
{
    public function transactional(callable $work): mixed
    {
        return DB::transaction(static fn (): mixed => $work());
    }
}
