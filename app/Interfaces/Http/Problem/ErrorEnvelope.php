<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Problem;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Builds the platform's shared error envelope (unero-shared-schemas
 * envelopes/error-envelope.schema.json): { error: { code, message, detail? }, request_id,
 * docs_url? }. Every error path renders through here so clients and the gateway parse one
 * shape and `error.code` (^[A-Z]+_[0-9]{3}$) is always present.
 */
final class ErrorEnvelope
{
    /** @param array<string,mixed> $detail */
    public static function render(
        Request $request,
        string $code,
        string $message,
        int $status,
        array $detail = [],
    ): JsonResponse {
        $body = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'request_id' => (string) $request->attributes->get('request_id', ''),
            'docs_url' => rtrim((string) config('unero.docs_url'), '/').'/'.$code,
        ];

        if ($detail !== []) {
            $body['error']['detail'] = $detail;
        }

        return response()->json($body, $status);
    }
}
