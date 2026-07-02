<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use App\Application\PasswordReset\Command\MaterializePasswordResetDeliveryCommand;
use App\Application\PasswordReset\Command\RequestPasswordResetCommand;
use App\Application\PasswordReset\MaterializePasswordResetDelivery;
use App\Application\PasswordReset\RequestPasswordReset;
use App\Interfaces\Http\Request\RequestPasswordResetRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Password reset — request side (2H-b1). Requesting is public and always returns 202 (no
 * enumeration). Materialising is an authenticated internal callback used by the notification service
 * to exchange the delivery_ref for the freshly-minted token + recipient email.
 */
final class PasswordResetController
{
    public function requestReset(RequestPasswordResetRequest $request, RequestPasswordReset $handler): JsonResponse
    {
        $handler->handle(new RequestPasswordResetCommand(
            email: (string) $request->string('email'),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => ['status' => 'accepted']], 202);
    }

    public function materialize(string $ref, Request $request, MaterializePasswordResetDelivery $handler): JsonResponse
    {
        /** @var array{id:string,type:string} $actor */
        $actor = $request->attributes->get('actor');

        $result = $handler->handle(new MaterializePasswordResetDeliveryCommand(
            deliveryRef: $ref,
            actorId: $actor['id'],
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => [
            'email' => $result->email,
            'token' => $result->token,
            'expires_at' => $result->expiresAt,
        ]]);
    }
}
