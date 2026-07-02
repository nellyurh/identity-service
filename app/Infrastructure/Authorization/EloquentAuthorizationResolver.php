<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization;

use App\Application\Auth\Result\ResolvedAuthorization;
use App\Application\Port\AuthorizationResolver;
use App\Domain\Identity\User\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

/**
 * Read-model adapter: resolves permission names by joining the user's roles to their granted
 * permissions in one query (distinct, name-ordered so tokens are stable), and reads authz_version
 * from the users row. Super-admin grants are already explicit (the seeder expands the wildcard),
 * so no wildcard expansion is needed here.
 */
final readonly class EloquentAuthorizationResolver implements AuthorizationResolver
{
    public function resolve(UserId $userId): ResolvedAuthorization
    {
        $permissions = DB::table('user_roles')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('user_roles.user_id', $userId->value)
            ->distinct()
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        $authzVersion = DB::table('users')->where('id', $userId->value)->value('authz_version');

        return new ResolvedAuthorization(
            array_values($permissions),
            is_numeric($authzVersion) ? (int) $authzVersion : 1,
        );
    }
}
