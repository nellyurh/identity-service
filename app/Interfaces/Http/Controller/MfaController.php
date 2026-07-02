<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use App\Application\Mfa\Command\ConfirmTotpCommand;
use App\Application\Mfa\Command\DisableMfaCommand;
use App\Application\Mfa\Command\EnrollTotpCommand;
use App\Application\Mfa\ConfirmTotp;
use App\Application\Mfa\DisableMfa;
use App\Application\Mfa\EnrollTotp;
use App\Interfaces\Http\Request\ConfirmTotpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MFA (TOTP) enrollment. Gateway-authenticated. Enroll returns the secret + otpauth URI once; confirm
 * activates the credential after a valid code. Thin: parse -> command -> service -> view.
 */
final class MfaController
{
    public function enrollTotp(string $id, Request $request, EnrollTotp $handler): JsonResponse
    {
        $result = $handler->handle(new EnrollTotpCommand(
            userId: $id,
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => [
            'secret' => $result->secret,
            'provisioning_uri' => $result->provisioningUri,
        ]]);
    }

    public function confirmTotp(string $id, ConfirmTotpRequest $request, ConfirmTotp $handler): JsonResponse
    {
        $result = $handler->handle(new ConfirmTotpCommand(
            userId: $id,
            code: (string) $request->string('code'),
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => ['enabled' => $result->enabled]]);
    }

    public function disableTotp(string $id, Request $request, DisableMfa $handler): JsonResponse
    {
        $result = $handler->handle(new DisableMfaCommand(
            userId: $id,
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => ['disabled' => $result->disabled]]);
    }

    private function actorId(Request $request): string
    {
        /** @var array{id:string,type:string} $actor */
        $actor = $request->attributes->get('actor');

        return $actor['id'];
    }
}
