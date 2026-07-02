<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class RegisterEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
    }

    /** @return array<string,string> */
    private function body(string $email = 'ada@unero.com', string $username = 'ada_l'): array
    {
        return ['email' => $email, 'username' => $username, 'password' => 'S3cret-pass'];
    }

    public function test_register_persists_user_and_emits_event(): void
    {
        $response = $this->postJson('/identity/register', $this->body(), ['Idempotency-Key' => 'k-1']);

        $response->assertCreated();
        $userId = $response->json('data.user_id');
        $this->assertIsString($userId);
        $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $userId);

        $this->assertDatabaseHas('users', ['email' => 'ada@unero.com', 'username' => 'ada_l', 'status' => 'active']);
        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'UserRegistered', 'aggregate_id' => $userId]);
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        $this->postJson('/identity/register', $this->body())
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_001');
    }

    public function test_invalid_payload_returns_validation_envelope(): void
    {
        $this->postJson('/identity/register', $this->body(email: 'not-an-email'), ['Idempotency-Key' => 'k-2'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_422')
            ->assertJsonStructure(['error' => ['code', 'message', 'detail'], 'request_id']);
    }

    public function test_duplicate_email_conflicts(): void
    {
        $this->postJson('/identity/register', $this->body(), ['Idempotency-Key' => 'k-3'])->assertCreated();

        $this->postJson('/identity/register', $this->body(username: 'ada_two'), ['Idempotency-Key' => 'k-4'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'USER_004');
    }
}
