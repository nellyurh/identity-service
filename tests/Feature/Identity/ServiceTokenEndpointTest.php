<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\TokenVerifier;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ServiceTokenEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    /** @return array{id:string,secret:string} */
    private function createAccount(string $name = 'wallet'): array
    {
        $data = $this->postJson('/identity/service-accounts', [
            'name' => $name, 'scopes' => ['wallet.credit', 'wallet.debit'],
        ], $this->admin + ['Idempotency-Key' => 'c1'])->assertCreated()->json('data');

        return ['id' => (string) $data['id'], 'secret' => (string) $data['secret']];
    }

    /** @return array<string,mixed> */
    private function decodeClaims(string $jwt): array
    {
        $segments = explode('.', $jwt);
        $claims = json_decode((string) base64_decode(strtr($segments[1], '-_', '+/'), true), true);

        return is_array($claims) ? $claims : [];
    }

    public function test_client_credentials_issues_a_scoped_service_token(): void
    {
        $account = $this->createAccount();

        $response = $this->postJson('/identity/service/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'wallet',
            'client_secret' => $account['secret'],
        ])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 900)
            ->assertJsonPath('data.scope', 'wallet.credit wallet.debit');

        $token = (string) $response->json('data.access_token');

        $claims = $this->decodeClaims($token);
        $this->assertSame('service', $claims['token_use']);
        $this->assertSame($account['id'], $claims['sub']);
        $this->assertSame(['wallet.credit', 'wallet.debit'], $claims['scopes']);

        $verifier = $this->app->make(TokenVerifier::class);
        $this->assertInstanceOf(TokenVerifier::class, $verifier);
        $verified = $verifier->verify($token, new DateTimeImmutable);
        $this->assertSame($account['id'], $verified->subject);
    }

    public function test_wrong_secret_is_generic_401(): void
    {
        $this->createAccount();

        $this->postJson('/identity/service/token', [
            'client_id' => 'wallet', 'client_secret' => 'not-the-secret',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SERVICE_004');
    }

    public function test_unknown_client_is_generic_401(): void
    {
        $this->postJson('/identity/service/token', [
            'client_id' => 'ghost', 'client_secret' => 'whatever',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SERVICE_004');
    }

    public function test_disabled_account_cannot_get_a_token(): void
    {
        $account = $this->createAccount();
        $this->postJson("/identity/service-accounts/{$account['id']}/disable", [], $this->admin + ['Idempotency-Key' => 'd1'])->assertOk();

        $this->postJson('/identity/service/token', [
            'client_id' => 'wallet', 'client_secret' => $account['secret'],
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SERVICE_004');
    }

    public function test_malformed_client_id_is_generic_401(): void
    {
        $this->postJson('/identity/service/token', [
            'client_id' => 'Bad Name!', 'client_secret' => 'whatever',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SERVICE_004');
    }

    public function test_missing_fields_is_422(): void
    {
        $this->postJson('/identity/service/token', ['client_id' => 'wallet'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_422');
    }
}
