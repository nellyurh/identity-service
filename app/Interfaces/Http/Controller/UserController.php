<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use App\Application\User\AssignRole;
use App\Application\User\ChangePassword;
use App\Application\User\Command\AssignRoleCommand;
use App\Application\User\Command\ChangePasswordCommand;
use App\Application\User\Command\DeleteUserCommand;
use App\Application\User\Command\DisableUserCommand;
use App\Application\User\Command\EnableUserCommand;
use App\Application\User\Command\RevokeRoleCommand;
use App\Application\User\DeleteUser;
use App\Application\User\DisableUser;
use App\Application\User\EnableUser;
use App\Application\User\GetUser;
use App\Application\User\Result\UserProfile;
use App\Application\User\RevokeRole;
use App\Domain\Identity\User\Exception\UserNotFound;
use App\Interfaces\Http\Request\AssignRoleRequest;
use App\Interfaces\Http\Request\ChangePasswordRequest;
use App\Interfaces\Http\Request\UserActionRequest;
use App\Interfaces\Http\Request\UserLookupRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administrative user surface behind auth.service. Route ids are ULID-constrained so a
 * malformed id is a 404 at routing (never a 500 from the UserId value object). Mutations run
 * behind the idempotency middleware; each maps a typed result DTO to the response envelope.
 */
final class UserController
{
    public function show(string $id, GetUser $query): JsonResponse
    {
        $profile = $query->byId($id) ?? throw UserNotFound::withId($id);

        return response()->json(['data' => $this->present($profile)]);
    }

    public function lookup(UserLookupRequest $request, GetUser $query): JsonResponse
    {
        $email = (string) $request->string('email');

        if ($email !== '') {
            $profile = $query->byEmail($email) ?? throw UserNotFound::withEmail($email);
        } else {
            $username = (string) $request->string('username');
            $profile = $query->byUsername($username) ?? throw UserNotFound::withUsername($username);
        }

        return response()->json(['data' => $this->present($profile)]);
    }

    public function changePassword(ChangePasswordRequest $request, ChangePassword $handler): JsonResponse
    {
        $actor = $this->actor($request);

        $profile = $handler->handle(new ChangePasswordCommand(
            userId: $actor['id'],
            currentPassword: (string) $request->string('current_password'),
            newPassword: (string) $request->string('new_password'),
            actorId: $actor['id'],
            actorType: $actor['type'],
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $this->present($profile)]);
    }

    public function disable(UserActionRequest $request, string $id, DisableUser $handler): JsonResponse
    {
        $actor = $this->actor($request);

        $profile = $handler->handle(new DisableUserCommand(
            userId: $id,
            actorId: $actor['id'],
            actorType: $actor['type'],
            requestId: (string) $request->attributes->get('request_id'),
            reason: $this->reason($request),
        ));

        return response()->json(['data' => $this->present($profile)]);
    }

    public function enable(UserActionRequest $request, string $id, EnableUser $handler): JsonResponse
    {
        $actor = $this->actor($request);

        $profile = $handler->handle(new EnableUserCommand(
            userId: $id,
            actorId: $actor['id'],
            actorType: $actor['type'],
            requestId: (string) $request->attributes->get('request_id'),
            reason: $this->reason($request),
        ));

        return response()->json(['data' => $this->present($profile)]);
    }

    public function destroy(UserActionRequest $request, string $id, DeleteUser $handler): JsonResponse
    {
        $actor = $this->actor($request);

        $profile = $handler->handle(new DeleteUserCommand(
            userId: $id,
            actorId: $actor['id'],
            actorType: $actor['type'],
            requestId: (string) $request->attributes->get('request_id'),
            reason: $this->reason($request),
        ));

        return response()->json(['data' => $this->present($profile)]);
    }

    public function assignRole(string $id, AssignRoleRequest $request, AssignRole $handler): JsonResponse
    {
        $actor = $this->actor($request);

        $profile = $handler->handle(new AssignRoleCommand(
            userId: $id,
            roleId: (string) $request->string('role_id'),
            actorId: $actor['id'],
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $this->present($profile)]);
    }

    public function revokeRole(string $id, string $roleId, Request $request, RevokeRole $handler): JsonResponse
    {
        $actor = $this->actor($request);

        $profile = $handler->handle(new RevokeRoleCommand(
            userId: $id,
            roleId: $roleId,
            actorId: $actor['id'],
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $this->present($profile)]);
    }

    /** @return array<string,mixed> */
    private function present(UserProfile $p): array
    {
        return [
            'user_id' => $p->userId,
            'email' => $p->email,
            'username' => $p->username,
            'status' => $p->status,
            'email_verified' => $p->emailVerified,
            'roles' => $p->roles,
            'authz_version' => $p->authzVersion,
            'created_at' => $p->createdAt,
            'updated_at' => $p->updatedAt,
        ];
    }

    /** @return array{id:string,type:string} */
    private function actor(Request $request): array
    {
        /** @var array{id:string,type:string} $actor */
        $actor = $request->attributes->get('actor');

        return $actor;
    }

    private function reason(Request $request): ?string
    {
        $reason = (string) $request->string('reason');

        return $reason === '' ? null : $reason;
    }
}
