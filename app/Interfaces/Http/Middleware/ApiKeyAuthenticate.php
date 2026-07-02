<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Middleware;

use App\Application\Port\Clock;
use App\Domain\Identity\ApiKey\ApiKey;
use App\Domain\Identity\ApiKey\Exception\ApiKeyAuthenticationFailed;
use App\Domain\Identity\ApiKey\Repository\ApiKeyRepository;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyPrefix;
use App\Domain\Identity\ServiceAccount\ValueObject\Scope;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Authenticates a request by API key: `Authorization: ApiKey unero_<env>_<prefix>.<secret>`. The
 * prefix (public, indexed) drives an O(1) lookup; the secret is verified constant-time against the
 * stored hash. Missing/malformed header, unknown prefix, wrong secret, expired, and revoked keys all
 * fail identically with 401 APIKEY_003 (no enumeration). An optional required scope (`apikey:<scope>`)
 * yields 403 APIKEY_002 when absent. On success the key's last_used_at is advanced (throttled) and the
 * acting principal is recorded.
 */
final readonly class ApiKeyAuthenticate
{
    public function __construct(
        private ApiKeyRepository $keys,
        private Clock $clock,
    ) {}

    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'ApiKey ')) {
            throw ApiKeyAuthenticationFailed::create();
        }

        $parsed = $this->parse(trim(substr($header, 7)));
        if ($parsed === null) {
            throw ApiKeyAuthenticationFailed::create();
        }

        try {
            $prefix = new ApiKeyPrefix($parsed['prefix']);
        } catch (InvalidArgumentException) {
            throw ApiKeyAuthenticationFailed::create();
        }

        $key = $this->keys->findByPrefix($prefix);
        $now = $this->clock->now();

        if (! $key instanceof ApiKey || ! $key->verifySecret($parsed['secret']) || ! $key->isUsable($now)) {
            throw ApiKeyAuthenticationFailed::create();
        }

        if ($requiredScope !== null && ! $key->hasScope(new Scope($requiredScope))) {
            throw new HttpException(403, 'APIKEY_002: Insufficient scope.');
        }

        $throttle = (int) config('unero.api_key.touch_throttle', 3600);
        if ($key->touch($now, $throttle)) {
            $this->keys->save($key);
        }

        $request->attributes->set('actor', ['id' => $key->id->value, 'type' => 'api_key']);
        $request->attributes->set('api_key_scopes', $key->scopes()->toArray());

        return $next($request);
    }

    /**
     * Split `unero_<env>_<prefix>.<secret>` into prefix + secret. The prefix is the token after the
     * last underscore of the part before the dot; the secret is everything after the dot.
     *
     * @return array{prefix:string,secret:string}|null
     */
    private function parse(string $credential): ?array
    {
        $dot = strpos($credential, '.');
        if ($dot === false || $dot === 0) {
            return null;
        }

        $left = substr($credential, 0, $dot);
        $secret = substr($credential, $dot + 1);
        if ($secret === '') {
            return null;
        }

        $underscore = strrpos($left, '_');
        if ($underscore === false) {
            return null;
        }

        $prefix = substr($left, $underscore + 1);
        if ($prefix === '') {
            return null;
        }

        return ['prefix' => $prefix, 'secret' => $secret];
    }
}
