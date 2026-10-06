<?php

use App\Http\Middleware\TenantContextMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            TenantContextMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->report(function (Throwable $e) {
            Log::error('[GlobalException] '.$e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => request()?->fullUrl(),
                'method' => request()?->method(),
                'user_id' => auth()->id(),
                'trace' => substr($e->getTraceAsString(), 0, 2000),
            ]);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (
                $e instanceof ValidationException ||
                $e instanceof AuthenticationException ||
                $e instanceof AuthorizationException ||
                $e instanceof HttpExceptionInterface
            ) {
                return null;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred: '.$e->getMessage(),
                    'error' => config('app.debug') ? $e->getMessage() : 'Server Error',
                ], 500);
            }

            if ($request->isMethodSafe() === false) {
                return back()->withInput()->with('error', 'Operation failed: '.$e->getMessage());
            }

            return null;
        });
    })->create();
