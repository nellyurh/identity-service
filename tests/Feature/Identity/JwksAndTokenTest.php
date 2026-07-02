<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use App\Application\Port\SigningKeyProvider;
use App\Application\Port\TokenVerifier;
use App\Infrastructure\Token\ConfigSigningKeyProvider;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class JwksAndTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($resource === false) {
            throw new RuntimeException('could not generate test key');
        }
        $privatePem = '';
        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);
        $publicPem = is_array($details) && isset($details['key']) ? (string) $details['key'] : '';

        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
        $this->app->singleton(
            SigningKeyProvider::class,
            static fn (): ConfigSigningKeyProvider => new ConfigSigningKeyProvider((string) $privatePem, $publicPem, 'test-kid'),
        );
    }

    public function test_jwks_endpoint_publishes_the_key(): void
    {
        $this->getJson('/.well-known/jwks.json')
            ->assertOk()
            ->assertJsonPath('keys.0.kid', 'test-kid')
            ->assertJsonPath('keys.0.kty', 'RSA')
            ->assertJsonPath('keys.0.alg', 'RS256');
    }

    public function test_login_issues_a_verifiable_access_token(): void
    {
        $userId = (string) $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->json('data.user_id');

        $response = $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 900);

        $token = (string) $response->json('data.access_token');
        $this->assertNotSame('', $token);

        $verifier = $this->app->make(TokenVerifier::class);
        $this->assertInstanceOf(TokenVerifier::class, $verifier);
        $verified = $verifier->verify($token, new DateTimeImmutable);
        $this->assertSame($userId, $verified->subject);
        $this->assertSame('access', $verified->claims['token_use']);
    }
}
