<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adiciona Cache-Control só em respostas 200 de GET — usado nos endpoints
 * públicos de listagem/detalhe (deputados, senadores, notícias, propostas),
 * que não mudam a cada segundo. Sem isso, cada navegação de volta a uma
 * tela já visitada refaz a chamada completa (banco incluso) mesmo quando o
 * dado é o mesmo de segundos atrás — o navegador/CDN podem responder do
 * cache local sem nem chegar ao backend.
 */
class CacheHeaders
{
    public function handle(Request $request, Closure $next, int $segundos = 60): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->getStatusCode() === 200) {
            $response->headers->set('Cache-Control', "public, max-age={$segundos}");
        }

        return $response;
    }
}
