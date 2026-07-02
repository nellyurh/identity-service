<?php

declare(strict_types=1);

use App\Interfaces\Http\Controller\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', [HealthController::class, 'live']);
Route::get('/readyz', [HealthController::class, 'ready']);

// Internal admin surface (user, role, permission, service-account, api-key management)
// is added under this authenticated group as each capability lands in later milestones.
// Public authentication endpoints (login, refresh, JWKS) will mount outside this group.
