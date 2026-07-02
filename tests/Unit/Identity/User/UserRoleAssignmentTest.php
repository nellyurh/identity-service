<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\User;

use App\Domain\Identity\Role\ValueObject\RoleId;
use App\Domain\Identity\User\Event\RoleAssigned;
use App\Domain\Identity\User\Event\RoleRemoved;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\HashedPassword;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Identity\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UserRoleAssignmentTest extends TestCase
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
            HashedPassword::fromHash('$argon2id$hash'),
            $this->now,
        );
        $user->releaseEvents();

        return $user;
    }

    public function test_assign_role_bumps_authz_version_and_records_event(): void
    {
        $user = $this->user();
        $role = new RoleId((string) new Ulid);

        $this->assertSame(1, $user->authzVersion());

        $user->assignRole($role, $this->now);

        $this->assertTrue($user->hasRole($role));
        $this->assertSame(2, $user->authzVersion());

        $events = $user->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(RoleAssigned::class, $events[0]);
        $this->assertSame(2, $events[0]->payload()['authz_version']);
    }

    public function test_assigning_an_already_held_role_is_a_noop(): void
    {
        $user = $this->user();
        $role = new RoleId((string) new Ulid);

        $user->assignRole($role, $this->now);
        $user->releaseEvents();
        $user->assignRole($role, $this->now);

        $this->assertSame(2, $user->authzVersion());
        $this->assertSame([], $user->releaseEvents());
    }

    public function test_revoke_role_bumps_authz_version_and_records_event(): void
    {
        $user = $this->user();
        $role = new RoleId((string) new Ulid);
        $user->assignRole($role, $this->now);
        $user->releaseEvents();

        $user->revokeRole($role, $this->now);

        $this->assertFalse($user->hasRole($role));
        $this->assertSame(3, $user->authzVersion());

        $events = $user->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(RoleRemoved::class, $events[0]);
    }

    public function test_revoking_a_role_not_held_is_a_noop(): void
    {
        $user = $this->user();

        $user->revokeRole(new RoleId((string) new Ulid), $this->now);

        $this->assertSame(1, $user->authzVersion());
        $this->assertSame([], $user->releaseEvents());
    }
}
