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

        // Já aproveita a mesma requisição pra começar a drenar a fila. Sem
        // --stop-when-empty: com o rate limit da IA (resumo-ia), um job que
        // esbarra no limite é devolvido pra fila com um "available_at" no
        // futuro — pra --stop-when-empty isso PARECE fila vazia (não há job
        // disponível AGORA), então o worker encerrava na primeira notícia
        // limitada e desperdiçava o resto da janela de 50s sem processar
        // mais nada. Sem essa flag, o --max-time continua limitando o tempo
        // da requisição, só que agora ele aproveita a janela inteira.
        Artisan::call('queue:work', [
            '--queue' => 'coleta,resumo',
            '--max-time' => 50,
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

        // Sem --stop-when-empty (ver comentário em executarPipelineNoticias):
        // esse endpoint é chamado com alta frequência exatamente pra drenar
        // o que ficou represado pelo rate limit da IA, então encerrar cedo
        // na primeira notícia limitada anula o propósito dele.
        Artisan::call('queue:work', [
            '--queue' => 'coleta,resumo',
            '--max-time' => 50,
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
