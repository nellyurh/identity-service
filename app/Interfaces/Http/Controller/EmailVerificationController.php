<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use App\Application\EmailVerification\Command\RequestEmailVerificationCommand;
use App\Application\EmailVerification\Command\VerifyEmailCommand;
use App\Application\EmailVerification\RequestEmailVerification;
use App\Application\EmailVerification\VerifyEmail;
use App\Interfaces\Http\Request\VerifyEmailRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Email verification. Requesting a token is gateway-authenticated and returns the raw token once to
 * the trusted caller for delivery (kept out of events). Verifying is public — the token is the proof.
 */
final class EmailVerificationController
{
    public function request(string $id, Request $request, RequestEmailVerification $handler): JsonResponse
    {
        /** @var array{id:string,type:string} $actor */
        $actor = $request->attributes->get('actor');

        $result = $handler->handle(new RequestEmailVerificationCommand(
            userId: $id,
            actorId: $actor['id'],
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => ['token' => $result->token, 'expires_at' => $result->expiresAt]]);
    }

    public function verify(VerifyEmailRequest $request, VerifyEmail $handler): JsonResponse
    {
        $result = $handler->handle(new VerifyEmailCommand(
            token: (string) $request->string('token'),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => ['user_id' => $result->userId, 'verified' => $result->verified]]);
    }
}
