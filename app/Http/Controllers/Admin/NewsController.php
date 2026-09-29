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
                ->when($busca !== '', fn ($query) => $query->where('title', 'ilike', "%{$busca}%"))
                ->orderByDesc('imported_at')
                ->paginate(15, [
                    'id', 'title', 'category', 'published', 'image_url',
                    'status_resumo', 'erro_resumo', 'tentativas_resumo', 'imported_at',
                ])
        );
    }

    /**
     * Quantas notícias o disparo manual busca por vez, POR FONTE — menor que
     * o teto do ciclo automático de 12h (30/fonte) de propósito: o admin
     * pode clicar a qualquer momento, inclusive perto de um ciclo
     * automático, então um lote menor evita empilhar resumo pendente demais
     * de uma vez. Baixado de 10 pra 5: com 2 fontes ativas, 10/fonte podia
     * somar até ~20 notícias novas num clique só — mais do que o rate limit
     * de 5/min da IA processa rápido, o que fazia parecer que o botão
     * "travava" (só estava demorando demais pra terminar).
     */
    private const LIMITE_COLETA_MANUAL = 5;

    /**
     * Gatilho manual do painel para o mesmo pipeline de notícias usado pelo
     * ciclo automático de 12h (ver SchedulerController::executarPipelineNoticias).
     * Não duplica a coleta: chama os mesmos comandos Artisan, só que
     * autenticado por login de admin em vez do token do scheduler.
     *
     * Independente do automático nos dois sentidos:
     * - --forcar ignora a janela mínima entre coletas da fonte, então o botão
     *   roda na hora mesmo logo depois de um ciclo automático (antes, dentro
     *   dessa janela a fonte era pulada e o clique não fazia nada);
     * - não bloqueia mais quando há resumos pendentes (antes respondia 409 e
     *   travava o botão sempre que o ciclo automático tinha deixado resumo na
     *   fila). Concorrência continua protegida pela trava por fonte dos Jobs
     *   e pela deduplicação — notícia repetida não é gravada duas vezes.
     *
     * Cada etapa é isolada num try/catch pra devolver uma mensagem legível
     * pro painel em vez de estourar uma exceção crua — o admin precisa saber
     * em qual das duas etapas (despachar a coleta ou drenar a fila) algo deu
     * errado, e o que já tinha sido concluído até ali.
     */
    public function collect(): JsonResponse
    {
        Artisan::call('noticias:limpar-pendentes-antigas');

        try {
            Artisan::call('noticias:coletar', ['--limite' => self::LIMITE_COLETA_MANUAL, '--forcar' => true]);
            $saidaColeta = trim(Artisan::output());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Não foi possível despachar a coleta de notícias: ' . $e->getMessage(),
            ], 500);
        }

        try {
            // IMPORTANTE: precisa de --stop-when-empty. Sem essa flag, o
            // worker fica vivo até --max-time esgotar mesmo sem trabalho de
            // verdade (ex: só falta esperar o rate limit liberar) — isso já
            // causou a plataforma matar essa requisição no meio de um job
            // (visto na tabela jobs: job "reservado", attempts incrementado,
            // nunca completado nem movido pra failed_jobs), deixando notícia
            // presa em "aguardando resumo" indefinidamente e o botão travado
            // (a trava por pendentes>0 nem deixa tentar de novo). --max-time
            // reduzido por segurança, bem abaixo de qualquer timeout de
            // gateway razoável.
            Artisan::call('queue:work', [
                '--queue' => 'coleta,resumo',
                '--stop-when-empty' => true,
                '--max-time' => 20,
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

    /**
     * Drena o que já está na fila, sem coletar nada novo — ao contrário de
     * collect(), não trava por pendentes>0 (é exatamente o oposto: existe
     * pra reduzir os pendentes). Cada chamada só tem uma janela curta e seca
     * (--max-time baixo, ver comentário em collect()), então o painel chama
     * isso repetidamente enquanto houver pendente, em vez de depender de um
     * clique só terminar tudo de uma vez — na prática, substitui a
     * necessidade do cron externo rodar certinho pra continuar drenando.
     */
    public function drain(): JsonResponse
    {
        Artisan::call('noticias:limpar-pendentes-antigas');

        Artisan::call('queue:work', [
            '--queue' => 'coleta,resumo',
            '--stop-when-empty' => true,
            '--max-time' => 20,
        ]);

        return response()->json([
            'status' => 'executado',
            'fila' => trim(Artisan::output()),
            'pendentes' => News::whereIn('status_resumo', ['pendente', 'em_processamento'])->count(),
        ]);
    }

    /**
     * Remoção manual pelo admin — ex: notícia irrelevante que passou pelo
     * filtro, ou lixo que ficou "aguardando resumo" e não vale a pena
     * esperar a limpeza automática de 8h.
     */
    public function destroy(int $id): JsonResponse
    {
        News::findOrFail($id)->delete();

        return response()->json(['message' => 'Notícia removida.']);
    }
}
