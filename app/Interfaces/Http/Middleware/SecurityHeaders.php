<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every API response. A JSON API should never be framed, sniffed, or
 * cached by intermediaries: token-bearing bodies in a shared cache are a credential leak. Responses
 * that explicitly opt into public caching (JWKS — verifiers MUST be able to cache the public keys,
 * ADR-ID-001) keep their own Cache-Control; everything else is forced to no-store. HSTS is sent only
 * over TLS (per spec it is ignored otherwise) — the edge also sets it; this is defense in depth.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
        }

        $cacheControl = (string) $response->headers->get('Cache-Control', '');
        if (! str_contains($cacheControl, 'public') && ! str_contains($cacheControl, 'max-age')) {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
