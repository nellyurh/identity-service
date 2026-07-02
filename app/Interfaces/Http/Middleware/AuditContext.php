<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Establishes correlation for the whole request: a stable request_id and correlation_id
 * available to logs, the outbox, and audit rows.
 */
final class AuditContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: 'req_'.Uuid::v7();
        $correlationId = $request->header('X-Correlation-Id') ?: $requestId;

        $request->attributes->set('request_id', $requestId);
        Context::add('request_id', $requestId);
        Context::add('correlation_id', $correlationId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
