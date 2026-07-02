<?php

declare(strict_types=1);

use App\Domain\Shared\Exception\DomainException;
use App\Interfaces\Http\Middleware\AuditContext;
use App\Interfaces\Http\Middleware\AuthenticateService;
use App\Interfaces\Http\Middleware\IdempotencyMiddleware;
use App\Interfaces\Http\Middleware\RequirePermission;
use App\Interfaces\Http\Problem\ErrorEnvelope;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            AuditContext::class,
        ]);
        $middleware->alias([
            'auth.service' => AuthenticateService::class,
            'idempotency' => IdempotencyMiddleware::class,
            'permission' => RequirePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every error path renders the shared error envelope (ERROR_CATALOG.md) so clients
        // and the gateway parse one shape and error.code is always present.

        // 1) Domain exceptions carry their own stable code + status + detail.
        $exceptions->render(fn (DomainException $e, $request) => ErrorEnvelope::render(
            $request, $e->errorCode(), $e->getMessage(), $e->httpStatus(), $e->detail(),
        ));

        // 2) Validation -> 422 with field errors as structured detail.
        $exceptions->render(fn (ValidationException $e, $request) => ErrorEnvelope::render(
            $request, 'VALIDATION_422', 'The given data was invalid.', 422, ['fields' => $e->errors()],
        ));

        // 3) HTTP exceptions (auth, idempotency, not-found). Messages of the form
        //    "CODE_NNN: message" carry a platform code; otherwise derive HTTP_<status>.
        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            $status = $e->getStatusCode();
            $message = $e->getMessage();
            if (preg_match('/^([A-Z]+_[0-9]{3}): (.*)$/', $message, $m) === 1) {
                return ErrorEnvelope::render($request, $m[1], $m[2], $status);
            }

            return ErrorEnvelope::render($request, 'HTTP_'.$status, $message !== '' ? $message : 'HTTP error.', $status);
        });

        // 4) Fallback: mask internals in production; keep Laravel's trace locally.
        $exceptions->render(function (Throwable $e, $request) {
            if ((bool) config('app.debug')) {
                return null;
            }

            return ErrorEnvelope::render($request, 'SERVER_500', 'Internal server error.', 500);
        });
    })
    ->create();
