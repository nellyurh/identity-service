<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\Token\RefreshToken;
use App\Domain\Identity\Token\RevocationReason;
use App\Domain\Identity\Token\ValueObject\FamilyId;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Repository\EloquentRefreshTokenRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class EloquentRefreshTokenRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function repo(): EloquentRefreshTokenRepository
    {
        return new EloquentRefreshTokenRepository(new OutboxWriter);
    }

    private function issue(EloquentRefreshTokenRepository $repo, FamilyId $family, string $secret, string $jti): RefreshToken
    {
        return RefreshToken::issue(
            $repo->nextIdentity(),
            new UserId((string) new Ulid),
            $family,
            hash('sha256', $secret),
            $jti,
            $this->now->modify('+30 days'),
            $this->now,
        );
    }

    public function test_save_persists_and_writes_token_issued_and_find_by_hash_round_trips(): void
    {
        $repo = $this->repo();
        $token = $this->issue($repo, $repo->nextFamilyIdentity(), 'secret-1', 'jti-1');

        $repo->save($token);

        $this->assertDatabaseHas('refresh_tokens', [
            'id' => $token->id->value,
            'token_hash' => hash('sha256', 'secret-1'),
            'access_jti' => 'jti-1',
            'revoked_at' => null,
        ]);
        $this->assertDatabaseHas('outbox_entries', [
            'event_type' => 'TokenIssued',
            'aggregate_type' => 'RefreshToken',
            'aggregate_id' => $token->id->value,
        ]);

        $found = $repo->findByHash(hash('sha256', 'secret-1'));
        $this->assertNotNull($found);
        $this->assertSame($token->id->value, $found?->id->value);
    }

    public function test_revoke_family_revokes_all_active_and_emits_one_event(): void
    {
        $repo = $this->repo();
        $family = $repo->nextFamilyIdentity();
        $a = $this->issue($repo, $family, 'secret-a', 'jti-a');
        $b = $this->issue($repo, $family, 'secret-b', 'jti-b');
        $repo->save($a);
        $repo->save($b);

        $repo->revokeFamily($family, RevocationReason::Logout, $this->now);

        $this->assertSame(0, DB::table('refresh_tokens')
            ->where('family_id', $family->value)
            ->whereNull('revoked_at')
            ->count());
        $this->assertSame(1, DB::table('outbox_entries')->where('event_type', 'TokenRevoked')->count());
    }

    public function test_revoke_family_is_idempotent(): void
    {
        $repo = $this->repo();
        $family = $repo->nextFamilyIdentity();
        $repo->save($this->issue($repo, $family, 'secret-a', 'jti-a'));

        $repo->revokeFamily($family, RevocationReason::Logout, $this->now);
        $repo->revokeFamily($family, RevocationReason::Logout, $this->now);

        $this->assertSame(1, DB::table('outbox_entries')->where('event_type', 'TokenRevoked')->count());
    }
}
