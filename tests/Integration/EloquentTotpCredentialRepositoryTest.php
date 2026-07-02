<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Port\SecretCipher;
use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Repository\EloquentTotpCredentialRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class EloquentTotpCredentialRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentTotpCredentialRepository $credentials;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->credentials = new EloquentTotpCredentialRepository(new OutboxWriter);
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    public function test_pending_then_confirm_emits_event_and_flips_lookup(): void
    {
        $userId = new UserId((string) new Ulid);
        $credential = TotpCredential::enroll($this->credentials->nextIdentity(), $userId, 'ciphertext', $this->now);
        $this->credentials->save($credential);

        $this->assertNotNull($this->credentials->findPendingForUser($userId));
        $this->assertNull($this->credentials->findActiveForUser($userId));

        $pending = $this->credentials->findPendingForUser($userId);
        $pending?->confirm($this->now);
        if ($pending instanceof TotpCredential) {
            $this->credentials->save($pending);
        }

        $this->assertNull($this->credentials->findPendingForUser($userId));
        $this->assertNotNull($this->credentials->findActiveForUser($userId));
        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'MFAEnabled', 'aggregate_type' => 'TotpCredential']);
    }

    public function test_delete_for_user(): void
    {
        $userId = new UserId((string) new Ulid);
        $this->credentials->save(TotpCredential::enroll($this->credentials->nextIdentity(), $userId, 'c', $this->now));

        $this->credentials->deleteForUser($userId);

        $this->assertNull($this->credentials->findPendingForUser($userId));
    }

    public function test_secret_cipher_round_trips(): void
    {
        $cipher = $this->app->make(SecretCipher::class);
        $this->assertInstanceOf(SecretCipher::class, $cipher);

        $ciphertext = $cipher->encrypt('JBSWY3DPEHPK3PXP');
        $this->assertNotSame('JBSWY3DPEHPK3PXP', $ciphertext);
        $this->assertSame('JBSWY3DPEHPK3PXP', $cipher->decrypt($ciphertext));
    }
}
