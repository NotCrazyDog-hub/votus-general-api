<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Fonte;
use App\Models\Legislator;
use App\Models\News;
use App\Models\Proposal;
use App\Models\SantinhoGeneration;
use App\Models\SiteVisit;
use App\Models\Suggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    private const ULTIMAS_NOTICIAS_LIMITE = 3;

    public function index(): JsonResponse
    {
        // Fonte::max() é uma agregação SQL crua: ao contrário de um atributo de
        // model, não passa pelo cast "datetime" do Eloquent, então volta como
        // string sem timezone (ex: "2026-09-16 14:29:46"). Sem o "Z" no final,
        // o `new Date(...)` do frontend interpretava isso como hora local do
        // navegador em vez de UTC — daí o horário aparecer errado no painel.
        $ultimaColetaEmRaw = Fonte::max('ultima_coleta_em');
        $ultimaColetaEm = $ultimaColetaEmRaw
            ? Carbon::parse($ultimaColetaEmRaw, 'UTC')
            : null;
        $fontesComFalha = Fonte::where('ativa', false)->count();

        return response()->json([
            'noticias' => [
                // "Publicadas" é o que realmente aparece no site (passou pelo
                // filtro de relevância do Votus); "total" inclui também o que
                // está pendente, falhou ou foi reprovado por não ter relação
                // com o propósito do Votus — útil pra operação, mas não deve
                // ser confundido com "quantas notícias o usuário vê".
                'total' => News::count(),
                'publicadas' => News::where('published', true)->count(),
                // Usado pra desabilitar o botão "Atualizar notícias" no
                // painel enquanto o resumo de um lote anterior ainda não
                // terminou (ver Admin\NewsController::collect).
                'pendentes' => News::whereIn('status_resumo', ['pendente', 'em_processamento'])->count(),
                'ultima_atualizacao_em' => $ultimaColetaEm?->toJSON(),
                // Aproximação: conta o que entrou desde a última coleta bem-sucedida de
                // qualquer fonte, já que a coleta roda em Jobs assíncronos por fonte e não
                // existe uma tabela de "execuções" para amarrar isso com exatidão.
                'adicionadas_na_ultima_execucao' => $ultimaColetaEm
                    ? News::where('imported_at', '>=', $ultimaColetaEm)->count()
                    : 0,
                'status' => $fontesComFalha > 0 ? 'com_falhas' : 'ok',
                'fontes_com_falha' => $fontesComFalha,
                'ultimas' => News::query()
                    ->orderByDesc('imported_at')
                    ->limit(self::ULTIMAS_NOTICIAS_LIMITE)
                    ->get(['id', 'title', 'category', 'published', 'status_resumo', 'erro_resumo', 'imported_at'])
                    ->map(fn (News $noticia) => [
                        'id' => $noticia->id,
                        'title' => $noticia->title,
                        'topicos' => $noticia->category ? [$noticia->category] : [],
                        'published' => $noticia->published,
                        'status_resumo' => $noticia->status_resumo,
                        'erro_resumo' => $noticia->erro_resumo,
                        'adicionada_em' => $noticia->imported_at,
                    ]),
            ],
            'dados_politicos' => [
                // Mesmo filtro aplicado no frontend público (DeputadosPage/
                // SenadoresPage): exclui suplentes/mandatos inativos, senão
                // esse número não bate com o que realmente aparece no site.
                'deputados' => Legislator::where('chamber', 'lower_house')->where('status', '!=', 'inactive')->count(),
                'senadores' => Legislator::where('chamber', 'senate')->where('status', '!=', 'inactive')->count(),
            ],
            'participacao' => [
                'santinhos_gerados' => SantinhoGeneration::count(),
                // "acessos registrados", não "visitantes únicos" — ver SiteVisitController.
                'acessos_registrados' => SiteVisit::count(),
            ],
            'moderacao' => [
                'propostas_publicadas' => Proposal::where('status', ProposalStatus::Published)->count(),
                'propostas_removidas' => Proposal::where('status', ProposalStatus::Removed)->count(),
            ],
            'sugestoes' => [
                'total' => Suggestion::count(),
            ],
        ]);
    }
}
