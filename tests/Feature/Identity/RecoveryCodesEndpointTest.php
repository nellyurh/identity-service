<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use App\Application\Port\TotpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakePasswordHasher;
use Tests\Support\FakeTotpProvider;
use Tests\TestCase;

final class RecoveryCodesEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
        $this->app->bind(TotpProvider::class, static fn (): FakeTotpProvider => new FakeTotpProvider);
    }

    /**
     * @return array{id:string,codes:list<string>}
     */
    private function registerAndEnable(): array
    {
        $userId = (string) $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated()->json('data.user_id');

        $this->postJson("/identity/users/{$userId}/mfa/totp/enroll", [], $this->admin + ['Idempotency-Key' => 'e1'])->assertOk();
        $codes = $this->postJson("/identity/users/{$userId}/mfa/totp/confirm", ['code' => FakeTotpProvider::VALID_CODE], $this->admin + ['Idempotency-Key' => 'c1'])
            ->assertOk()
            ->json('data.recovery_codes');

        /** @var list<string> $codes */
        return ['id' => $userId, 'codes' => $codes];
    }

    public function test_confirm_returns_a_batch_of_recovery_codes(): void
    {
        $result = $this->registerAndEnable();

        $this->assertCount(10, $result['codes']);
        $this->assertSame(10, DB::table('recovery_codes')->where('user_id', $result['id'])->count());
    }

    public function test_regenerate_replaces_the_previous_batch(): void
    {
        $result = $this->registerAndEnable();
        $oldFirst = $result['codes'][0];

        $newCodes = $this->postJson("/identity/users/{$result['id']}/mfa/recovery-codes", [], $this->admin + ['Idempotency-Key' => 'rc1'])
            ->assertOk()
            ->json('data.recovery_codes');

        $this->assertCount(10, $newCodes);
        $this->assertNotContains($oldFirst, $newCodes);

        // the old code's hash is gone; only the new batch remains
        $this->assertDatabaseMissing('recovery_codes', ['code_hash' => hash('sha256', $oldFirst)]);
        $this->assertSame(10, DB::table('recovery_codes')->where('user_id', $result['id'])->count());
    }

    public function test_disable_clears_recovery_codes(): void
    {
        $result = $this->registerAndEnable();

        $this->postJson("/identity/users/{$result['id']}/mfa/totp/disable", [], $this->admin + ['Idempotency-Key' => 'd1'])->assertOk();

        $this->assertSame(0, DB::table('recovery_codes')->where('user_id', $result['id'])->count());
    }

    public function test_regenerate_without_active_mfa_is_404(): void
    {
        $userId = (string) $this->postJson('/identity/register', [
            'email' => 'bob@unero.com', 'username' => 'bob_k', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-2'])->assertCreated()->json('data.user_id');

        $this->postJson("/identity/users/{$userId}/mfa/recovery-codes", [], $this->admin + ['Idempotency-Key' => 'rc1'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MFA_005');
    }
}
