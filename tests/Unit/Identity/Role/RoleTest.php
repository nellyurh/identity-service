<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Role;

use App\Domain\Identity\Permission\ValueObject\PermissionId;
use App\Domain\Identity\Role\Event\PermissionGranted;
use App\Domain\Identity\Role\Event\PermissionRevoked;
use App\Domain\Identity\Role\Event\RoleCreated;
use App\Domain\Identity\Role\Exception\SystemRoleImmutable;
use App\Domain\Identity\Role\Role;
use App\Domain\Identity\Role\ValueObject\RoleId;
use App\Domain\Identity\Role\ValueObject\RoleName;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class RoleTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    public function test_create_records_role_created(): void
    {
        $role = Role::create(new RoleId((string) new Ulid), new RoleName('member'), null, false, $this->now);

        $events = $role->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(RoleCreated::class, $events[0]);
        $this->assertFalse($events[0]->payload()['is_system']);
    }

    public function test_grant_is_idempotent_and_records_once(): void
    {
        $role = Role::create(new RoleId((string) new Ulid), new RoleName('member'), null, false, $this->now);
        $role->releaseEvents();
        $perm = new PermissionId((string) new Ulid);

        $role->grantPermission($perm, $this->now);
        $role->grantPermission($perm, $this->now);

        $this->assertCount(1, $role->permissions());
        $this->assertTrue($role->hasPermission($perm));

        $events = $role->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PermissionGranted::class, $events[0]);
    }

    public function test_revoke_removes_and_records_only_when_held(): void
    {
        $role = Role::create(new RoleId((string) new Ulid), new RoleName('member'), null, false, $this->now);
        $perm = new PermissionId((string) new Ulid);
        $role->grantPermission($perm, $this->now);
        $role->releaseEvents();

        $role->revokePermission($perm, $this->now);
        $this->assertFalse($role->hasPermission($perm));
        $events = $role->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PermissionRevoked::class, $events[0]);

        // revoking again is a no-op with no event
        $role->revokePermission($perm, $this->now);
        $this->assertSame([], $role->releaseEvents());
    }

    public function test_system_role_cannot_be_renamed(): void
    {
        $role = Role::create(new RoleId((string) new Ulid), new RoleName('super_admin'), 'root', true, $this->now);

        $this->expectException(SystemRoleImmutable::class);
        $role->rename(new RoleName('root'));
    }
}
