<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\User;

use App\Domain\Identity\User\Event\UserLocked;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\HashedPassword;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Identity\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UserLockoutTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function user(): User
    {
        $user = User::register(
            new UserId((string) new Ulid),
            new Email('ada@unero.com'),
            new Username('ada_l'),
            HashedPassword::fromHash('hash'),
            $this->now,
        );
        $user->releaseEvents();

        return $user;
    }

    public function test_locks_at_threshold_and_emits_event(): void
    {
        $user = $this->user();

        for ($i = 0; $i < 4; $i++) {
            $user->recordFailedLogin(5, 900, $this->now);
        }
        $this->assertFalse($user->isLocked($this->now));
        $this->assertSame(4, $user->failedLoginCount());
        $this->assertSame([], $user->releaseEvents());

        $user->recordFailedLogin(5, 900, $this->now);

        $this->assertTrue($user->isLocked($this->now));
        $this->assertSame(0, $user->failedLoginCount()); // fresh threshold after expiry
        $this->assertEquals($this->now->modify('+900 seconds'), $user->lockedUntil());

        $events = $user->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserLocked::class, $events[0]);
    }

    public function test_lock_expires_by_time(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 5; $i++) {
            $user->recordFailedLogin(5, 900, $this->now);
        }

        $this->assertTrue($user->isLocked($this->now->modify('+899 seconds')));
        $this->assertFalse($user->isLocked($this->now->modify('+901 seconds')));
    }

    public function test_successful_login_resets_counter_and_lock(): void
    {
        $user = $this->user();
        $user->recordFailedLogin(5, 900, $this->now);
        $user->recordFailedLogin(5, 900, $this->now);

        $user->recordSuccessfulLogin($this->now);

        $this->assertSame(0, $user->failedLoginCount());
        $this->assertNull($user->lockedUntil());
    }

    public function test_successful_login_with_clean_state_is_noop(): void
    {
        $user = $this->user();
        $before = $user->updatedAt();

        $user->recordSuccessfulLogin($this->now->modify('+1 hour'));

        $this->assertEquals($before, $user->updatedAt()); // no needless write signal
    }
}
