<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class NewsController extends Controller
{
    /**
     * Lista notícias pra tela dedicada do painel — ao contrário do endpoint
     * público (App\Http\Controllers\NewsController::index), não filtra por
     * published: o admin precisa ver também as que ainda estão pendentes ou
     * falharam no resumo, com o motivo do erro quando houver.
     */
    public function index(Request $request): JsonResponse
    {
        $busca = trim((string) $request->query('search', ''));

        return response()->json(
            News::query()
                ->when($busca !== '', fn ($query) => $query->where('title', 'like', "%{$busca}%"))
                ->orderByDesc('imported_at')
                ->paginate(15, [
                    'id', 'title', 'category', 'published',
                    'status_resumo', 'erro_resumo', 'tentativas_resumo', 'imported_at',
                ])
        );
    }

    /**
     * Quantas notícias o disparo manual busca por vez — menor que o teto do
     * ciclo automático de 12h (30/fonte) de propósito: o admin pode clicar a
     * qualquer momento, inclusive perto de um ciclo automático, então um
     * lote menor evita empilhar resumo pendente demais de uma vez.
     */
    private const LIMITE_COLETA_MANUAL = 15;

    /**
     * Gatilho manual do painel para o mesmo pipeline de notícias já usado
     * pelo cron externo (ver SchedulerController::executarPipelineNoticias).
     * Não duplica a coleta: chama os mesmos comandos Artisan, só que
     * autenticado por login de admin em vez do token do scheduler.
     *
     * Trava enquanto houver notícia pendente/em processamento: sem isso, um
     * segundo clique (ou o ciclo automático caindo no meio) empilharia mais
     * resumo em cima do que a fila (rate limited a 10/min) ainda nem
     * terminou de processar. O ciclo automático de 12h continua rodando
     * normalmente — essa trava é só pro botão manual.
     *
     * Cada etapa é isolada num try/catch pra devolver uma mensagem legível
     * pro painel em vez de estourar uma exceção crua — o admin precisa saber
     * em qual das duas etapas (despachar a coleta ou drenar a fila) algo deu
     * errado, e o que já tinha sido concluído até ali.
     */
    public function collect(): JsonResponse
    {
        $pendentes = News::whereIn('status_resumo', ['pendente', 'em_processamento'])->count();

        if ($pendentes > 0) {
            return response()->json([
                'status' => 'aguardando',
                'message' => "Ainda há {$pendentes} notícia(s) sendo processada(s) pelo resumo de IA. Aguarde terminar antes de buscar mais.",
                'pendentes' => $pendentes,
            ], 409);
        }

        try {
            Artisan::call('noticias:coletar', ['--limite' => self::LIMITE_COLETA_MANUAL]);
            $saidaColeta = trim(Artisan::output());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Não foi possível despachar a coleta de notícias: ' . $e->getMessage(),
            ], 500);
        }

        try {
            Artisan::call('queue:work', [
                '--queue' => 'coleta,resumo',
                '--stop-when-empty' => true,
                '--max-time' => 50,
            ]);
            $saidaFila = trim(Artisan::output());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'parcial',
                'message' => 'A coleta foi despachada, mas o processamento da fila falhou: ' . $e->getMessage(),
                'coleta' => $saidaColeta,
            ], 500);
        }

        return response()->json([
            'status' => 'executado',
            'coleta' => $saidaColeta,
            'fila' => $saidaFila,
        ]);
    }
}
