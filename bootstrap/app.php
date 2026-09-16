<?php

use App\Http\Middleware\EnsureIsAdmin;
use App\Http\Middleware\VerificaTokenInterno;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'internal.token' => VerificaTokenInterno::class,
            'admin' => EnsureIsAdmin::class,
        ]);

        // API pura, sem tela de login web: sem isso, o middleware "auth:sanctum"
        // tenta redirecionar pra uma rota "login" inexistente sempre que o
        // cliente não manda um Accept: application/json explícito (é o caso do
        // fetch() puro do apiClient.ts do frontend), e isso vira 500 em vez de
        // 401 — só descoberto testando /api/admin/dashboard sem token.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
