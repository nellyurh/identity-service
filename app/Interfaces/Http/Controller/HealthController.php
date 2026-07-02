<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Liveness and readiness probes. Readiness checks the stores identity-service depends on
 * on its hot path: PostgreSQL (state) and Redis (sessions, token cache, rate limiting).
 * A failed dependency returns 503 with the failing component named.
 */
final class HealthController
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->check(static fn (): mixed => DB::connection()->getPdo()),
            'redis' => $this->check(static fn (): mixed => Redis::connection()->ping()),
        ];

        $ready = ! in_array('down', $checks, true);

        return response()->json(
            ['status' => $ready ? 'ready' : 'not_ready', 'checks' => $checks],
            $ready ? 200 : 503,
        );
    }

    private function check(callable $probe): string
    {
        try {
            $probe();

            return 'up';
        } catch (Throwable) {
            return 'down';
        }
    }
}
