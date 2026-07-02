<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use App\Application\ServiceAccount\Command\CreateServiceAccountCommand;
use App\Application\ServiceAccount\Command\DisableServiceAccountCommand;
use App\Application\ServiceAccount\Command\RotateServiceAccountCredentialCommand;
use App\Application\ServiceAccount\CreateServiceAccount;
use App\Application\ServiceAccount\DisableServiceAccount;
use App\Application\ServiceAccount\GetServiceAccount;
use App\Application\ServiceAccount\ListServiceAccounts;
use App\Application\ServiceAccount\Result\ServiceAccountCredential;
use App\Application\ServiceAccount\Result\ServiceAccountView;
use App\Application\ServiceAccount\RotateServiceAccountCredential;
use App\Interfaces\Http\Request\CreateServiceAccountRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin surface for service accounts (gateway-authenticated). Create and rotate return the client
 * secret exactly once, inside `data.secret`; list/read never expose it. Thin: parse -> command ->
 * service -> view.
 */
final class ServiceAccountController
{
    public function index(ListServiceAccounts $query): JsonResponse
    {
        $data = array_map(static fn (ServiceAccountView $v): array => $v->toArray(), $query->handle());

        return response()->json(['data' => $data]);
    }

    public function show(string $id, GetServiceAccount $query): JsonResponse
    {
        return response()->json(['data' => $query->handle($id)->toArray()]);
    }

    public function store(CreateServiceAccountRequest $request, CreateServiceAccount $handler): JsonResponse
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

        $credential = $handler->handle(new CreateServiceAccountCommand(
            name: (string) $request->string('name'),
            scopes: $scopes,
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $this->present($credential)], 201);
    }

    public function rotate(string $id, Request $request, RotateServiceAccountCredential $handler): JsonResponse
    {
        $credential = $handler->handle(new RotateServiceAccountCredentialCommand(
            serviceAccountId: $id,
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $this->present($credential)]);
    }

    public function disable(string $id, Request $request, DisableServiceAccount $handler): JsonResponse
    {
        $view = $handler->handle(new DisableServiceAccountCommand(
            serviceAccountId: $id,
            actorId: $this->actorId($request),
            requestId: (string) $request->attributes->get('request_id'),
        ));

        return response()->json(['data' => $view->toArray()]);
    }

    /** @return array<string,mixed> */
    private function present(ServiceAccountCredential $credential): array
    {
        return $credential->account->toArray() + ['secret' => $credential->secret];
    }

    private function actorId(Request $request): string
    {
        /** @var array{id:string,type:string} $actor */
        $actor = $request->attributes->get('actor');

        return $actor['id'];
    }
}
