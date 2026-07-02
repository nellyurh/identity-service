<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\PasswordReset\PasswordReset;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Repository\EloquentPasswordResetRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class EloquentPasswordResetRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentPasswordResetRepository $resets;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resets = new EloquentPasswordResetRepository(new OutboxWriter);
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function make(UserId $userId, string $ref): PasswordReset
    {
        return PasswordReset::create($this->resets->nextIdentity(), $userId, $ref, $this->now->modify('+1 hour'), $this->now);
    }

    public function test_save_emits_event_and_find_by_ref_round_trips(): void
    {
        $userId = new UserId((string) new Ulid);
        $this->resets->save($this->make($userId, 'ref-abc'));

        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'PasswordResetRequested', 'aggregate_type' => 'PasswordReset']);

        $found = $this->resets->findByDeliveryRef('ref-abc');
        $this->assertNotNull($found);
        $this->assertTrue($found?->isDeliverable($this->now));
    }

    public function test_materialise_then_find_by_token_hash(): void
    {
        $userId = new UserId((string) new Ulid);
        $reset = $this->make($userId, 'ref-xyz');
        $this->resets->save($reset);

        $reloaded = $this->resets->findByDeliveryRef('ref-xyz');
        $reloaded?->materialize(hash('sha256', 'the-token'), $this->now);
        if ($reloaded instanceof PasswordReset) {
            $this->resets->save($reloaded);
        }

        $byHash = $this->resets->findByTokenHash(hash('sha256', 'the-token'));
        $this->assertNotNull($byHash);
        $this->assertTrue($byHash?->isRedeemable($this->now));
    }

    public function test_invalidate_for_user_drops_unused(): void
    {
        $userId = new UserId((string) new Ulid);
        $this->resets->save($this->make($userId, 'ref-1'));

        $this->resets->invalidateForUser($userId);

        $this->assertNull($this->resets->findByDeliveryRef('ref-1'));
    }
}
