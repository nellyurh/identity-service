<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use App\Application\Permission\Command\DefinePermissionCommand;
use App\Application\Permission\DefinePermission;
use App\Application\Permission\ListPermissions;
use App\Application\Permission\Result\PermissionView;
use App\Interfaces\Http\Request\DefinePermissionRequest;
use Illuminate\Http\JsonResponse;

/**
 * Admin surface for the permission catalog. Listing is a plain read; defining adds a non-system
 * permission (system permissions come only from the seeder). Gateway-authenticated (auth.service).
 */
final class PermissionController
{
    public function index(ListPermissions $query): JsonResponse
    {
        $data = array_map(
            static fn (PermissionView $view): array => $view->toArray(),
            $query->handle(),
        );

        return response()->json(['data' => $data]);
    }

    public function store(DefinePermissionRequest $request, DefinePermission $handler): JsonResponse
    {
        /** @var array{id:string,type:string} $actor */
        $actor = $request->attributes->get('actor');

        $view = $handler->handle(new DefinePermissionCommand(
            name: (string) $request->string('name'),
            description: $request->filled('description') ? (string) $request->string('description') : null,
            actorId: $actor['id'],
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $view->toArray()], 201);
    }
}
