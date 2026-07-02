<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\Permission\Permission;
use App\Domain\Identity\Permission\ValueObject\PermissionName;
use App\Domain\Identity\Role\Role;
use App\Domain\Identity\Role\ValueObject\RoleName;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\HashedPassword;
use App\Domain\Identity\User\ValueObject\Username;
use App\Infrastructure\Authorization\EloquentAuthorizationResolver;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Repository\EloquentPermissionRepository;
use App\Infrastructure\Persistence\Repository\EloquentRoleRepository;
use App\Infrastructure\Persistence\Repository\EloquentUserRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentAuthorizationResolverTest extends TestCase
{
    use RefreshDatabase;

    private EloquentUserRepository $users;

    private EloquentRoleRepository $roles;

    private EloquentPermissionRepository $permissions;

    private EloquentAuthorizationResolver $resolver;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new EloquentUserRepository(new OutboxWriter);
        $this->roles = new EloquentRoleRepository(new OutboxWriter);
        $this->permissions = new EloquentPermissionRepository;
        $this->resolver = new EloquentAuthorizationResolver;
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function permission(string $name): Permission
    {
        $permission = Permission::define($this->permissions->nextIdentity(), new PermissionName($name), null, true);
        $this->permissions->save($permission);

        return $permission;
    }

    public function test_resolves_deduplicated_ordered_permissions_and_authz_version(): void
    {
        $read = $this->permission('user.read');
        $create = $this->permission('user.create');
        $audit = $this->permission('audit.read');

        // two roles sharing user.read so the distinct/union is exercised
        $editor = Role::create($this->roles->nextIdentity(), new RoleName('editor'), null, false, $this->now);
        $editor->grantPermission($read->id, $this->now);
        $editor->grantPermission($create->id, $this->now);
        $this->roles->save($editor);

        $viewer = Role::create($this->roles->nextIdentity(), new RoleName('viewer'), null, false, $this->now);
        $viewer->grantPermission($read->id, $this->now);
        $viewer->grantPermission($audit->id, $this->now);
        $this->roles->save($viewer);

        $user = User::register($this->users->nextIdentity(), new Email('ada@unero.com'), new Username('ada_l'), HashedPassword::fromHash('h'), $this->now);
        $this->users->save($user);
        $user->assignRole($editor->id, $this->now);
        $user->assignRole($viewer->id, $this->now);
        $this->users->save($user);

        $resolved = $this->resolver->resolve($user->id);

        $this->assertSame(['audit.read', 'user.create', 'user.read'], $resolved->permissions);
        $this->assertSame(3, $resolved->authzVersion);
    }

    public function test_user_with_no_roles_resolves_empty_permissions(): void
    {
        $user = User::register($this->users->nextIdentity(), new Email('bob@unero.com'), new Username('bob_x'), HashedPassword::fromHash('h'), $this->now);
        $this->users->save($user);

        $resolved = $this->resolver->resolve($user->id);

        $this->assertSame([], $resolved->permissions);
        $this->assertSame(1, $resolved->authzVersion);
    }
}
