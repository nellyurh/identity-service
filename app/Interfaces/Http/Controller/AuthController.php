<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use App\Application\Auth\Command\CompleteMfaLoginCommand;
use App\Application\Auth\Command\IntrospectCommand;
use App\Application\Auth\Command\LoginCommand;
use App\Application\Auth\Command\LogoutCommand;
use App\Application\Auth\Command\RefreshCommand;
use App\Application\Auth\CompleteMfaLogin;
use App\Application\Auth\IntrospectToken;
use App\Application\Auth\LoginUser;
use App\Application\Auth\LogoutUser;
use App\Application\Auth\RefreshTokens;
use App\Application\Auth\Result\LoginResult;
use App\Application\Auth\Result\MfaChallengeIssued;
use App\Application\ServiceAccount\Command\IssueServiceTokenCommand;
use App\Application\ServiceAccount\IssueServiceToken;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\RegisterUser;
use App\Interfaces\Http\Request\IntrospectRequest;
use App\Interfaces\Http\Request\LoginRequest;
use App\Interfaces\Http\Request\LogoutRequest;
use App\Interfaces\Http\Request\MfaLoginRequest;
use App\Interfaces\Http\Request\RefreshRequest;
use App\Interfaces\Http\Request\RegisterRequest;
use App\Interfaces\Http\Request\ServiceTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

/**
 * Public authentication surface. Registration is idempotent; login verifies credentials and
 * returns the principal plus a short-lived RS256 access token and a rotating refresh token.
 * Refresh exchanges/rotates the pair; logout revokes the whole session family. Thin by design:
 * parse -> command -> application service -> typed result -> JSON.
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

    public function login(LoginRequest $request, LoginUser $handler): JsonResponse
    {
        $outcome = $handler->handle(new LoginCommand(
            email: (string) $request->string('email'),
            password: (string) $request->string('password'),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        $challenge = $outcome->challenge;
        if ($challenge instanceof MfaChallengeIssued) {
            return response()->json(['data' => [
                'mfa_required' => true,
                'challenge_token' => $challenge->challengeToken,
                'expires_in' => $challenge->expiresIn,
            ]]);
        }

        $session = $outcome->session;
        if (! $session instanceof LoginResult) {
            throw new LogicException('Login outcome had neither a session nor a challenge.');
        }

        return response()->json(['data' => $this->sessionData($session)]);
    }

    public function completeMfa(MfaLoginRequest $request, CompleteMfaLogin $handler): JsonResponse
    {
        $result = $handler->handle(new CompleteMfaLoginCommand(
            challengeToken: (string) $request->string('challenge_token'),
            code: (string) $request->string('code'),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $this->sessionData($result)]);
    }

    /** @return array<string,mixed> */
    private function sessionData(LoginResult $result): array
    {
        return [
            'user_id' => $result->userId,
            'status' => $result->status,
            'email_verified' => $result->emailVerified,
            'access_token' => $result->accessToken,
            'token_type' => $result->tokenType,
            'expires_in' => $result->expiresIn,
            'refresh_token' => $result->refreshToken,
            'refresh_expires_in' => $result->refreshExpiresIn,
        ];
    }

    public function refresh(RefreshRequest $request, RefreshTokens $handler): JsonResponse
    {
        $result = $handler->handle(new RefreshCommand(
            refreshToken: (string) $request->string('refresh_token'),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => [
            'access_token' => $result->accessToken,
            'token_type' => $result->tokenType,
            'expires_in' => $result->expiresIn,
            'refresh_token' => $result->refreshToken,
            'refresh_expires_in' => $result->refreshExpiresIn,
        ]]);
    }

    public function logout(LogoutRequest $request, LogoutUser $handler): JsonResponse
    {
        $result = $handler->handle(new LogoutCommand(
            refreshToken: (string) $request->string('refresh_token'),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => ['user_id' => $result->userId]]);
    }

    public function serviceToken(ServiceTokenRequest $request, IssueServiceToken $handler): JsonResponse
    {
        $result = $handler->handle(new IssueServiceTokenCommand(
            clientId: (string) $request->string('client_id'),
            clientSecret: (string) $request->string('client_secret'),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => [
            'access_token' => $result->accessToken,
            'token_type' => $result->tokenType,
            'expires_in' => $result->expiresIn,
            'scope' => implode(' ', $result->scopes),
        ]]);
    }

    public function introspect(IntrospectRequest $request, IntrospectToken $handler): JsonResponse
    {
        $result = $handler->handle(new IntrospectCommand(
            token: (string) $request->string('token'),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        $data = ['active' => $result->active];
        if ($result->active) {
            $data += [
                'sub' => $result->subject,
                'jti' => $result->jti,
                'token_use' => $result->tokenUse,
                'exp' => $result->expiresAt,
                'permissions' => $result->permissions,
                'authz_ver' => $result->authzVersion,
            ];
        }

        return response()->json(['data' => $data]);
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
