<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RateLimitMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('ratelimit:test-bucket,3,60')->post(
            '/test/limited',
            static fn () => response()->json(['ok' => true]),
        );
    }

    public function test_blocks_after_max_attempts_within_window(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/test/limited')->assertOk();
        }

        $this->postJson('/test/limited')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_001');
    }

    public function test_identifier_key_counts_alongside_ip(): void
    {
        // The email identifier is hashed into its own key; both keys are counted per request.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/test/limited', ['email' => 'ada@unero.com'])->assertOk();
        }

        $this->postJson('/test/limited', ['email' => 'ada@unero.com'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_001');
    }

    public function test_public_credential_routes_carry_the_limiter(): void
    {
        $expected = [
            'identity/login' => 'ratelimit:login,10,60',
            'identity/login/mfa' => 'ratelimit:mfa,10,60',
            'identity/service/token' => 'ratelimit:service-token,20,60',
            'identity/auth/password/reset-request' => 'ratelimit:reset-request,5,60',
            'identity/auth/password/reset' => 'ratelimit:reset,10,60',
            'identity/email/verify' => 'ratelimit:verify-email,10,60',
            'identity/auth/refresh' => 'ratelimit:refresh,30,60',
            'identity/register' => 'ratelimit:register,10,60',
        ];

        $routes = collect(Route::getRoutes()->getRoutes())
            ->mapWithKeys(static fn ($route): array => [$route->uri() => $route->gatherMiddleware()]);

        foreach ($expected as $uri => $middleware) {
            $this->assertArrayHasKey($uri, $routes->all(), "route {$uri} missing");
            $this->assertContains($middleware, $routes[$uri], "route {$uri} lacks {$middleware}");
        }
    }
}
