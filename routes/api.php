<?php

declare(strict_types=1);

use App\Interfaces\Http\Controller\ApiKeyController;
use App\Interfaces\Http\Controller\AuthController;
use App\Interfaces\Http\Controller\EmailVerificationController;
use App\Interfaces\Http\Controller\HealthController;
use App\Interfaces\Http\Controller\JwksController;
use App\Interfaces\Http\Controller\MfaController;
use App\Interfaces\Http\Controller\PasswordResetController;
use App\Interfaces\Http\Controller\PermissionController;
use App\Interfaces\Http\Controller\RoleController;
use App\Interfaces\Http\Controller\ServiceAccountController;
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
    Route::post('login/mfa', [AuthController::class, 'completeMfa']);
    Route::post('service/token', [AuthController::class, 'serviceToken']);
    Route::post('email/verify', [EmailVerificationController::class, 'verify']);
    Route::post('auth/password/reset-request', [PasswordResetController::class, 'requestReset']);
    Route::post('auth/password/reset', [PasswordResetController::class, 'reset']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Internal admin surface — gateway-authenticated (auth.service resolves the actor).
    Route::middleware('auth.service')->group(function (): void {
        // Token introspection for services doing high-value operations (RFC 7662 shape):
        // stateless verify + jti-denylist check. Not idempotency-keyed (a pure read).
        Route::post('tokens/introspect', [AuthController::class, 'introspect']);

        // Password reset delivery: the notification service exchanges a delivery_ref for the
        // freshly-minted token + email. Authenticated internal callback; naturally single-use.
        Route::post('internal/password-reset/deliveries/{ref}/materialize', [PasswordResetController::class, 'materialize'])
            ->where('ref', '[A-Za-z0-9_-]+');

        Route::get('users/{id}', [UserController::class, 'show'])->whereUlid('id');
        Route::get('users', [UserController::class, 'lookup']);

        // Permission catalog (RBAC reference data).
        Route::get('permissions', [PermissionController::class, 'index']);

        // Roles (RBAC).
        Route::get('roles', [RoleController::class, 'index']);
        Route::get('roles/{id}', [RoleController::class, 'show'])->whereUlid('id');

        // Service accounts (service-to-service identities).
        Route::get('service-accounts', [ServiceAccountController::class, 'index']);
        Route::get('service-accounts/{id}', [ServiceAccountController::class, 'show'])->whereUlid('id');

        // API keys (long-lived programmatic credentials).
        Route::get('api-keys', [ApiKeyController::class, 'index']);

        // Mutations are idempotent.
        Route::middleware('idempotency')->group(function (): void {
            Route::post('permissions', [PermissionController::class, 'store']);
            Route::post('roles', [RoleController::class, 'store']);
            Route::patch('roles/{id}', [RoleController::class, 'update'])->whereUlid('id');
            Route::post('roles/{id}/permissions', [RoleController::class, 'grant'])->whereUlid('id');
            Route::delete('roles/{id}/permissions/{permission}', [RoleController::class, 'revoke'])
                ->whereUlid('id')
                ->where('permission', '[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*');
            Route::post('change-password', [UserController::class, 'changePassword']);
            Route::post('users/{id}/disable', [UserController::class, 'disable'])->whereUlid('id');
            Route::post('users/{id}/enable', [UserController::class, 'enable'])->whereUlid('id');
            Route::delete('users/{id}', [UserController::class, 'destroy'])->whereUlid('id');
            Route::post('users/{id}/roles', [UserController::class, 'assignRole'])->whereUlid('id');
            Route::delete('users/{id}/roles/{roleId}', [UserController::class, 'revokeRole'])
                ->whereUlid('id')->whereUlid('roleId');
            Route::post('service-accounts', [ServiceAccountController::class, 'store']);
            Route::post('service-accounts/{id}/rotate', [ServiceAccountController::class, 'rotate'])->whereUlid('id');
            Route::post('service-accounts/{id}/disable', [ServiceAccountController::class, 'disable'])->whereUlid('id');
            Route::post('api-keys', [ApiKeyController::class, 'store']);
            Route::post('api-keys/{id}/rotate', [ApiKeyController::class, 'rotate'])->whereUlid('id');
            Route::delete('api-keys/{id}', [ApiKeyController::class, 'destroy'])->whereUlid('id');
            Route::post('users/{id}/email/verification-request', [EmailVerificationController::class, 'request'])->whereUlid('id');
            Route::post('users/{id}/mfa/totp/enroll', [MfaController::class, 'enrollTotp'])->whereUlid('id');
            Route::post('users/{id}/mfa/totp/confirm', [MfaController::class, 'confirmTotp'])->whereUlid('id');
            Route::post('users/{id}/mfa/totp/disable', [MfaController::class, 'disableTotp'])->whereUlid('id');
        });
    });
});
