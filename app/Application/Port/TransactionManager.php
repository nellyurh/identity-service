<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Infrastructure\Transaction\LaravelTransactionManager;

/**
 * Boundary for atomic units of work. Application services depend on this instead of any
 * framework transaction API, so the Application layer stays free of Infrastructure. The
 * adapter (Infrastructure) runs the closure inside a real database transaction; the
 * closure's return value is passed through.
 *
 * @see LaravelTransactionManager
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param  callable():T  $work
     * @return T
     */
    public function transactional(callable $work): mixed;
}
