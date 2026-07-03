<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\EmailVerification\EmailVerificationToken;
use App\Domain\Identity\PasswordReset\PasswordReset;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Repository\EloquentEmailVerificationTokenRepository;
use App\Infrastructure\Persistence\Repository\EloquentPasswordResetRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

/**
 * Single-use semantics for email-verification and password-reset credentials are enforced by
 * conditional updates (rows-affected checked), never SELECT-then-UPDATE. Each race is simulated by
 * two stale reads of the same row: exactly one caller wins.
 */
final class AtomicOneTimeCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    public function test_verification_token_consume_can_only_be_won_once(): void
    {
        $repo = new EloquentEmailVerificationTokenRepository;
        $userId = new UserId((string) new Ulid);
        $repo->save(EmailVerificationToken::create($repo->nextIdentity(), $userId, hash('sha256', 'v'), $this->now->modify('+1 hour'), $this->now));

        $first = $repo->findByHash(hash('sha256', 'v'));
        $second = $repo->findByHash(hash('sha256', 'v'));

        $this->assertTrue($first instanceof EmailVerificationToken && $repo->consume($first, $this->now));
        $this->assertFalse($second instanceof EmailVerificationToken && $repo->consume($second, $this->now));
    }

    public function test_expired_verification_token_cannot_be_consumed(): void
    {
        $repo = new EloquentEmailVerificationTokenRepository;
        $userId = new UserId((string) new Ulid);
        $repo->save(EmailVerificationToken::create($repo->nextIdentity(), $userId, hash('sha256', 'x'), $this->now->modify('-1 second'), $this->now->modify('-1 hour')));

        $token = $repo->findByHash(hash('sha256', 'x'));

        $this->assertFalse($token instanceof EmailVerificationToken && $repo->consume($token, $this->now));
    }

    public function test_reset_materialisation_can_only_be_won_once(): void
    {
        $repo = new EloquentPasswordResetRepository(new OutboxWriter);
        $userId = new UserId((string) new Ulid);
        $repo->save(PasswordReset::create($repo->nextIdentity(), $userId, 'ref-race', $this->now->modify('+1 hour'), $this->now));

        // two concurrent materialisations, each holding a stale read and its own freshly-minted token
        $first = $repo->findByDeliveryRef('ref-race');
        $second = $repo->findByDeliveryRef('ref-race');

        $this->assertTrue($first instanceof PasswordReset && $repo->materialize($first, hash('sha256', 'token-A'), $this->now));
        $this->assertFalse($second instanceof PasswordReset && $repo->materialize($second, hash('sha256', 'token-B'), $this->now));

        // the winner's token is live; the loser's was never bound to the row
        $this->assertNotNull($repo->findByTokenHash(hash('sha256', 'token-A')));
        $this->assertNull($repo->findByTokenHash(hash('sha256', 'token-B')));
    }

    public function test_reset_consume_can_only_be_won_once(): void
    {
        $repo = new EloquentPasswordResetRepository(new OutboxWriter);
        $userId = new UserId((string) new Ulid);
        $reset = PasswordReset::create($repo->nextIdentity(), $userId, 'ref-consume', $this->now->modify('+1 hour'), $this->now);
        $repo->save($reset);

        $deliverable = $repo->findByDeliveryRef('ref-consume');
        $this->assertTrue($deliverable instanceof PasswordReset && $repo->materialize($deliverable, hash('sha256', 't'), $this->now));

        $first = $repo->findByTokenHash(hash('sha256', 't'));
        $second = $repo->findByTokenHash(hash('sha256', 't'));

        $this->assertTrue($first instanceof PasswordReset && $repo->consume($first, $this->now));
        $this->assertFalse($second instanceof PasswordReset && $repo->consume($second, $this->now));
    }

    public function test_unmaterialised_reset_cannot_be_consumed(): void
    {
        $repo = new EloquentPasswordResetRepository(new OutboxWriter);
        $userId = new UserId((string) new Ulid);
        $reset = PasswordReset::create($repo->nextIdentity(), $userId, 'ref-raw', $this->now->modify('+1 hour'), $this->now);
        $repo->save($reset);

        $loaded = $repo->findByDeliveryRef('ref-raw');

        // no token has ever been bound; consumption must be impossible
        $this->assertFalse($loaded instanceof PasswordReset && $repo->consume($loaded, $this->now));
    }
}
