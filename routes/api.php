<?php

declare(strict_types=1);

use App\Interfaces\Http\Controller\AuthController;
use App\Interfaces\Http\Controller\HealthController;
use App\Interfaces\Http\Controller\JwksController;
use App\Interfaces\Http\Controller\PermissionController;
use App\Interfaces\Http\Controller\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', [HealthController::class, 'live']);
Route::get('/readyz', [HealthController::class, 'ready']);

// Public JWKS — other services fetch this to verify access tokens offline.
Route::get('/.well-known/jwks.json', [JwksController::class, 'index']);

Route::prefix('identity')->group(function (): void {
    // Public authentication surface. Refresh/logout carry no idempotency key: the refresh
    // token is itself single-use, so replay is defined by rotation, not by a client key.
    Route::post('register', [AuthController::class, 'register'])->middleware('idempotency');
    Route::post('login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Internal admin surface — gateway-authenticated (auth.service resolves the actor).
    Route::middleware('auth.service')->group(function (): void {
        // Token introspection for services doing high-value operations (RFC 7662 shape):
        // stateless verify + jti-denylist check. Not idempotency-keyed (a pure read).
        Route::post('tokens/introspect', [AuthController::class, 'introspect']);

        Route::get('users/{id}', [UserController::class, 'show'])->whereUlid('id');
        Route::get('users', [UserController::class, 'lookup']);

        // Permission catalog (RBAC reference data).
        Route::get('permissions', [PermissionController::class, 'index']);

        // Mutations are idempotent.
        Route::middleware('idempotency')->group(function (): void {
            Route::post('permissions', [PermissionController::class, 'store']);
            Route::post('change-password', [UserController::class, 'changePassword']);
            Route::post('users/{id}/disable', [UserController::class, 'disable'])->whereUlid('id');
            Route::post('users/{id}/enable', [UserController::class, 'enable'])->whereUlid('id');
            Route::delete('users/{id}', [UserController::class, 'destroy'])->whereUlid('id');
        });
    });
});
