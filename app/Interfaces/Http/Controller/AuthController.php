<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use App\Application\User\AuthenticateUser;
use App\Application\User\Command\AuthenticateUserCommand;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\RegisterUser;
use App\Interfaces\Http\Request\LoginRequest;
use App\Interfaces\Http\Request\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public authentication surface. Registration is idempotent; login verifies credentials and
 * returns the principal (access/refresh tokens are issued once the auth milestone lands).
 * Thin by design: parse -> command -> application service -> typed result -> JSON.
 */
final class AuthController
{
    public function register(RegisterRequest $request, RegisterUser $handler): JsonResponse
    {
        [$actorId, $actorType] = $this->actor($request);

        $result = $handler->handle(new RegisterUserCommand(
            email: (string) $request->string('email'),
            username: (string) $request->string('username'),
            password: (string) $request->string('password'),
            actorId: $actorId,
            actorType: $actorType,
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => ['user_id' => $result->userId]], 201);
    }

    public function login(LoginRequest $request, AuthenticateUser $handler): JsonResponse
    {
        $principal = $handler->handle(new AuthenticateUserCommand(
            email: (string) $request->string('email'),
            password: (string) $request->string('password'),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => [
            'user_id' => $principal->userId,
            'status' => $principal->status,
            'email_verified' => $principal->emailVerified,
        ]]);
    }

    /**
     * Registration is public: if the gateway resolved an actor (an admin creating a user) use
     * it, otherwise attribute the action to the anonymous self-registrant.
     *
     * @return array{0:string,1:string}
     */
    private function actor(Request $request): array
    {
        $actorId = $request->header('X-Actor-Id');
        $actorType = (string) $request->header('X-Actor-Type', 'user');

        if ($actorId === null || ! in_array($actorType, ['user', 'service'], true)) {
            return ['anonymous', 'user'];
        }

        return [$actorId, $actorType];
    }
}
