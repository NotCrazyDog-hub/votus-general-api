<?php

use App\Http\Middleware\CacheHeaders;
use App\Http\Middleware\EnsureIsAdmin;
use App\Http\Middleware\VerificaTokenInterno;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$domainCommandPaths = glob(
    __DIR__.'/../app/Domains/*/Console/Commands'
) ?: [];

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands($domainCommandPaths)
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'internal.token' => VerificaTokenInterno::class,
            'admin' => EnsureIsAdmin::class,
            'cache.headers' => CacheHeaders::class,
        ]);

        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->create();