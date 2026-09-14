<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificaTokenInterno
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = config('services.scheduler.token');
        $recebido = (string) ($request->header('X-Scheduler-Token') ?? $request->query('token') ?? '');

        if (empty($esperado) || !hash_equals($esperado, $recebido)) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        return $next($request);
    }
}
