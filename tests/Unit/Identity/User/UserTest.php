<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\User;

use App\Domain\Identity\User\Event\PasswordChanged;
use App\Domain\Identity\User\Event\UserActivated;
use App\Domain\Identity\User\Event\UserDisabled;
use App\Domain\Identity\User\Event\UserRegistered;
use App\Domain\Identity\User\Exception\AccountNotActive;
use App\Domain\Identity\User\Exception\EmailAlreadyVerified;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\UserStatus;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\HashedPassword;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Identity\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UserTest extends TestCase
{
    private function newUser(): User
    {
        return User::register(
            new UserId((string) new Ulid),
            new Email('Ada@Unero.com'),
            new Username('ada_l'),
            HashedPassword::fromHash('$argon2id$hash'),
            new DateTimeImmutable('2026-07-02T10:00:00+00:00'),
        );
    }

    public function test_register_starts_active_unverified_and_records_event(): void
    {
        $user = $this->newUser();

        $this->assertSame(UserStatus::Active, $user->status());
        $this->assertFalse($user->isEmailVerified());
        $this->assertSame('ada@unero.com', $user->email()->value);

        $events = $user->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserRegistered::class, $events[0]);
        $this->assertSame('UserRegistered', $events[0]->eventType());
        $this->assertSame('ada@unero.com', $events[0]->payload()['email']);
        $this->assertFalse($events[0]->payload()['email_verified']);

        $this->assertSame([], $user->releaseEvents(), 'events drain once');
    }

    public function test_verify_email_activates_and_is_single_use(): void
    {
        $user = $this->newUser();
        $user->releaseEvents();

        $user->verifyEmail(new DateTimeImmutable('2026-07-02T11:00:00+00:00'));
        $this->assertTrue($user->isEmailVerified());
        $events = $user->releaseEvents();
        $this->assertInstanceOf(UserActivated::class, $events[0]);

        $this->expectException(EmailAlreadyVerified::class);
        $user->verifyEmail(new DateTimeImmutable('2026-07-02T12:00:00+00:00'));
    }

    public function test_change_password_records_event_and_swaps_hash(): void
    {
        $user = $this->newUser();
        $user->releaseEvents();

        $user->changePassword(HashedPassword::fromHash('$argon2id$newhash'), new DateTimeImmutable);
        $events = $user->releaseEvents();
        $this->assertInstanceOf(PasswordChanged::class, $events[0]);
        $this->assertTrue($user->passwordHash()->equals(HashedPassword::fromHash('$argon2id$newhash')));
    }

    public function test_disabled_user_cannot_authenticate(): void
    {
        $user = $this->newUser();
        $user->releaseEvents();

        $user->disable(new DateTimeImmutable);
        $this->assertSame(UserStatus::Disabled, $user->status());
        $this->assertInstanceOf(UserDisabled::class, $user->releaseEvents()[0]);

        $this->expectException(AccountNotActive::class);
        $user->assertCanAuthenticate();
    }

    public function test_active_user_can_authenticate(): void
    {
        $user = $this->newUser();
        $user->assertCanAuthenticate();
        $this->addToAssertionCount(1);
    }

    public function test_deleted_user_cannot_be_re_enabled(): void
    {
        $user = $this->newUser();
        $user->softDelete(new DateTimeImmutable);
        $this->assertSame(UserStatus::Deleted, $user->status());

        $this->expectException(AccountNotActive::class);
        $user->enable(new DateTimeImmutable);
    }
}
