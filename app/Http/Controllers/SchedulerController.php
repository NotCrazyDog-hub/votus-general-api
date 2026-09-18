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
        // IMPORTANTE: precisa de --stop-when-empty aqui. Sem essa flag, o
        // worker fica vivo até --max-time esgotar mesmo sem ter mais nada
        // pra fazer (ex: só falta esperar o rate limit liberar) — e isso já
        // causou o processo inteiro (essa requisição HTTP) ser encerrado à
        // força pela plataforma antes do fim, deixando job "reservado" sem
        // nunca completar nem falhar (visto direto na tabela jobs: attempts
        // incrementado, nunca removido). --max-time reduzido por segurança,
        // bem abaixo de qualquer timeout de gateway razoável.
        Artisan::call('queue:work', [
            '--queue' => 'coleta,resumo',
            '--stop-when-empty' => true,
            '--max-time' => 20,
        ]);
        $saidaFila = Artisan::output();

        Artisan::call('noticias:limpar-pendentes-antigas');

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

        // Ver comentário em executarPipelineNoticias: --stop-when-empty é
        // obrigatório, senão o worker fica preso até --max-time mesmo sem
        // trabalho de verdade, arriscando ser morto pela plataforma no meio
        // de um job. Chamado com alta frequência (a cada poucos minutos),
        // então mesmo saindo mais cedo o backlog é drenado aos poucos.
        Artisan::call('queue:work', [
            '--queue' => 'coleta,resumo',
            '--stop-when-empty' => true,
            '--max-time' => 20,
        ]);
        $saidaFila = Artisan::output();

        Artisan::call('noticias:limpar-pendentes-antigas');

        return response()->json([
            'status' => 'executado',
            'fila' => trim($saidaFila),
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
