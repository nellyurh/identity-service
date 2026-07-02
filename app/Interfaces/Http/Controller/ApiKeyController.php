<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use App\Application\ApiKey\Command\CreateApiKeyCommand;
use App\Application\ApiKey\Command\RevokeApiKeyCommand;
use App\Application\ApiKey\Command\RotateApiKeyCommand;
use App\Application\ApiKey\CreateApiKey;
use App\Application\ApiKey\ListApiKeys;
use App\Application\ApiKey\Result\ApiKeyView;
use App\Application\ApiKey\RevokeApiKey;
use App\Application\ApiKey\RotateApiKey;
use App\Interfaces\Http\Request\CreateApiKeyRequest;
use App\Interfaces\Http\Request\ListApiKeysRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin surface for API keys (gateway-authenticated). Create returns the full key exactly once,
 * inside `data.key`; list/read never expose it. Thin: parse -> command -> service -> view.
 */
final class ApiKeyController
{
    public function index(ListApiKeysRequest $request, ListApiKeys $query): JsonResponse
    {
        $views = $query->handle(
            (string) $request->string('owner_type'),
            (string) $request->string('owner_id'),
        );

        return response()->json(['data' => array_map(static fn (ApiKeyView $v): array => $v->toArray(), $views)]);
    }

    public function store(CreateApiKeyRequest $request, CreateApiKey $handler): JsonResponse
    {
        $scopes = [];
        $raw = $request->input('scopes');
        if (is_array($raw)) {
            foreach ($raw as $scope) {
                if (is_string($scope)) {
                    $scopes[] = $scope;
                }
            }
        }

        $result = $handler->handle(new CreateApiKeyCommand(
            name: (string) $request->string('name'),
            ownerType: (string) $request->string('owner_type'),
            ownerId: (string) $request->string('owner_id'),
            scopes: $scopes,
            expiresAt: $request->filled('expires_at') ? (string) $request->string('expires_at') : null,
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $result->key->toArray() + ['key' => $result->fullKey]], 201);
    }

    public function destroy(string $id, Request $request, RevokeApiKey $handler): JsonResponse
    {
        $view = $handler->handle(new RevokeApiKeyCommand(
            apiKeyId: $id,
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $view->toArray()]);
    }

    public function rotate(string $id, Request $request, RotateApiKey $handler): JsonResponse
    {
        $result = $handler->handle(new RotateApiKeyCommand(
            apiKeyId: $id,
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $result->replacement->toArray() + [
            'key' => $result->fullKey,
            'replaced_key_id' => $result->rotatedKeyId,
            'grace_expires_at' => $result->graceExpiresAt,
        ]]);
    }

    private function actorId(Request $request): string
    {
        /** @var array{id:string,type:string} $actor */
        $actor = $request->attributes->get('actor');

        return $actor['id'];
    }
}
