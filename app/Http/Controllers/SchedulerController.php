<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SchedulerController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json(['status' => 'alive']);
    }

    /**
     * Disparado externamente (cron-job.org) a cada 12h em produção.
     * Só despacha os Jobs de coleta — o resumo respeita rate limiting
     * (10/min) e por isso é drenado à parte, por `processarFilaNoticias`,
     * chamado com muito mais frequência (ex: a cada 5-10 min). Se os dois
     * estivessem no mesmo endpoint, uma coleta de 12h em 12h nunca daria
     * tempo de resumir tudo antes da próxima leva de notícias chegar.
     */
    public function executarPipelineNoticias(Request $request): JsonResponse
    {
        if (!$this->tokenValido($request)) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        Artisan::call('noticias:coletar');
        $saidaColeta = Artisan::output();

        // Já aproveita a mesma requisição pra começar a drenar a fila.
        Artisan::call('queue:work', [
            '--queue' => 'coleta,resumo',
            '--stop-when-empty' => true,
            '--max-time' => 50,
        ]);
        $saidaFila = Artisan::output();

        return response()->json([
            'status' => 'executado',
            'coleta' => trim($saidaColeta),
            'fila' => trim($saidaFila),
        ]);
    }

    /**
     * Disparado externamente (cron-job.org) com alta frequência (ex: a cada
     * 5-10 min) só para continuar drenando o que a coleta das últimas 12h
     * deixou pendente na fila de resumo, respeitando o rate limiting da IA.
     */
    public function processarFilaNoticias(Request $request): JsonResponse
    {
        if (!$this->tokenValido($request)) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        Artisan::call('queue:work', [
            '--queue' => 'coleta,resumo',
            '--stop-when-empty' => true,
            '--max-time' => 50,
        ]);

        return response()->json([
            'status' => 'executado',
            'fila' => trim(Artisan::output()),
        ]);
    }

    private function tokenValido(Request $request): bool
    {
        $esperado = config('services.scheduler.token');

        if (empty($esperado)) {
            return false;
        }

        $recebido = (string) ($request->header('X-Scheduler-Token') ?? $request->query('token') ?? '');

        return hash_equals($esperado, $recebido);
    }
}
