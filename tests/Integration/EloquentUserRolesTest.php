<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\Role\Role;
use App\Domain\Identity\Role\ValueObject\RoleName;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\HashedPassword;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Identity\User\ValueObject\Username;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Repository\EloquentRoleRepository;
use App\Infrastructure\Persistence\Repository\EloquentUserRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentUserRolesTest extends TestCase
{
    use RefreshDatabase;

    private EloquentUserRepository $users;

    private EloquentRoleRepository $roles;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new EloquentUserRepository(new OutboxWriter);
        $this->roles = new EloquentRoleRepository;
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function newUser(): User
    {
        $user = User::register(
            $this->users->nextIdentity(),
            new Email('ada@unero.com'),
            new Username('ada_l'),
            HashedPassword::fromHash('argon2id$stored'),
            $this->now,
        );
        $this->users->save($user);

        return $user;
    }

    private function newRole(string $name): Role
    {
        $role = Role::create($this->roles->nextIdentity(), new RoleName($name), null, false);
        $this->roles->save($role);

        return $role;
    }

    public function test_assigning_a_role_persists_pivot_authz_version_and_event(): void
    {
        $user = $this->newUser();
        $role = $this->newRole('editor');

        $user->assignRole($role->id, $this->now);
        $this->users->save($user);

        $this->assertDatabaseHas('users', ['id' => $user->id->value, 'authz_version' => 2]);
        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id->value, 'role_id' => $role->id->value, 'tenant_id' => '']);
        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'RoleAssigned', 'aggregate_type' => 'User']);

        $reloaded = $this->users->getById(new UserId($user->id->value));
        $this->assertTrue($reloaded->hasRole($role->id));
        $this->assertSame(2, $reloaded->authzVersion());
    }

    public function test_revoking_a_role_removes_the_pivot_row(): void
    {
        $user = $this->newUser();
        $role = $this->newRole('editor');
        $user->assignRole($role->id, $this->now);
        $this->users->save($user);

        $reloaded = $this->users->getById(new UserId($user->id->value));
        $reloaded->revokeRole($role->id, $this->now);
        $this->users->save($reloaded);

        $this->assertDatabaseMissing('user_roles', ['user_id' => $user->id->value, 'role_id' => $role->id->value]);
        $this->assertDatabaseHas('users', ['id' => $user->id->value, 'authz_version' => 3]);
    }
}
