<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Middleware;

use App\Infrastructure\Persistence\Model\IdempotencyKeyModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Uid\Ulid;

/**
 * Enforces the idempotency contract: every mutating endpoint requires an Idempotency-Key.
 * Same key + same body -> the stored response, no side effect. Same key + different body
 * -> 409 IDEMPOTENCY_002. Mirrors config-service.
 */
final class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if (! $key) {
            throw new HttpException(400, 'IDEMPOTENCY_001: Idempotency-Key header missing on a mutating endpoint.');
        }

        $hash = hash('sha256', $request->getMethod().$request->getRequestUri().$request->getContent());
        $existing = IdempotencyKeyModel::query()->where('idempotency_key', $key)->first();

        if ($existing !== null) {
            if ($existing->request_hash !== $hash) {
                throw new HttpException(409, 'IDEMPOTENCY_002: Idempotency-Key reused with a different request body.');
            }

            return response()->json(
                json_decode((string) $existing->response_body, true),
                (int) $existing->response_code,
            );
        }

        $response = $next($request);

        IdempotencyKeyModel::query()->create([
            'id' => (string) new Ulid,
            'idempotency_key' => $key,
            'request_hash' => $hash,
            'response_code' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
            'created_at' => now()->toImmutable(),
        ]);

        return $response;
    }
}
