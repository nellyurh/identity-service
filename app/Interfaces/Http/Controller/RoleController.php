<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use App\Application\Role\Command\CreateRoleCommand;
use App\Application\Role\Command\GrantPermissionCommand;
use App\Application\Role\Command\RevokePermissionCommand;
use App\Application\Role\Command\UpdateRoleCommand;
use App\Application\Role\CreateRole;
use App\Application\Role\GetRole;
use App\Application\Role\GrantPermission;
use App\Application\Role\ListRoles;
use App\Application\Role\Result\RoleView;
use App\Application\Role\RevokePermission;
use App\Application\Role\UpdateRole;
use App\Interfaces\Http\Request\CreateRoleRequest;
use App\Interfaces\Http\Request\GrantPermissionRequest;
use App\Interfaces\Http\Request\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin surface for roles: list/read, create/update (non-system names), and grant/revoke catalog
 * permissions. Gateway-authenticated (auth.service). Thin: parse -> command -> service -> view.
 */
final class RoleController
{
    public function index(ListRoles $query): JsonResponse
    {
        $data = array_map(static fn (RoleView $view): array => $view->toArray(), $query->handle());

        return response()->json(['data' => $data]);
    }

    public function show(string $id, GetRole $query): JsonResponse
    {
        return response()->json(['data' => $query->handle($id)->toArray()]);
    }

    public function store(CreateRoleRequest $request, CreateRole $handler): JsonResponse
    {
        $view = $handler->handle(new CreateRoleCommand(
            name: (string) $request->string('name'),
            description: $request->filled('description') ? (string) $request->string('description') : null,
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $view->toArray()], 201);
    }

    public function update(string $id, UpdateRoleRequest $request, UpdateRole $handler): JsonResponse
    {
        $descriptionProvided = $request->has('description');

        $view = $handler->handle(new UpdateRoleCommand(
            roleId: $id,
            name: $request->has('name') ? (string) $request->string('name') : null,
            description: $descriptionProvided && $request->filled('description') ? (string) $request->string('description') : null,
            descriptionProvided: $descriptionProvided,
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $view->toArray()]);
    }

    public function grant(string $id, GrantPermissionRequest $request, GrantPermission $handler): JsonResponse
    {
        $view = $handler->handle(new GrantPermissionCommand(
            roleId: $id,
            permissionName: (string) $request->string('permission'),
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $view->toArray()]);
    }

    public function revoke(string $id, string $permission, Request $request, RevokePermission $handler): JsonResponse
    {
        $view = $handler->handle(new RevokePermissionCommand(
            roleId: $id,
            permissionName: $permission,
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $view->toArray()]);
    }

    private function actorId(Request $request): string
    {
        /** @var array{id:string,type:string} $actor */
        $actor = $request->attributes->get('actor');

        return $actor['id'];
    }
}
