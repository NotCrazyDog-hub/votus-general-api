<?php

namespace App\Http\Controllers;

use App\Http\Resources\NewsResource;
use App\Models\News;
use App\Services\News\CicloAutomaticoNoticias;
use App\Services\News\SelecionadorDestaqueNoticia;
use App\Services\News\ValidadorImagemNoticia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'original_summary' => 'nullable|string',
            'ai_summary' => 'required|string',
            'url' => 'required|url',
            'image_url' => 'nullable|url',
            'site_logo_url' => 'nullable|url',
            'source' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'published_at' => 'required|date',
            'relevance_score' => 'required|integer|min:0|max:10',
            'keywords' => 'required|array',
            'keywords.*' => 'string',
            'published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Mesma regra do pipeline de coleta: sem imagem válida, não entra no
        // banco (ver ValidadorImagemNoticia). Checado antes de gravar.
        $motivoImagem = app(ValidadorImagemNoticia::class)->motivoInvalida($data['image_url'] ?? null);

        if ($motivoImagem !== null) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => ['image_url' => ["Notícia sem imagem válida ({$motivoImagem}) não é aceita."]],
            ], 422);
        }

        $news = News::firstOrCreate(
            ['url' => $data['url']],
            $data
        );

        return response()->json([
            'message' => $news->wasRecentlyCreated ? 'Notícia criada' : 'Notícia já existia',
            'data' => $news,
        ], $news->wasRecentlyCreated ? 201 : 200);
    }

    private const SORTABLE_COLUMNS = ['published_at', 'imported_at', 'relevance_score', 'created_at'];

    public function index(Request $request, CicloAutomaticoNoticias $ciclo, SelecionadorDestaqueNoticia $destaque)
    {
        // Rede de segurança do ciclo de 12h (ver CicloAutomaticoNoticias).
        $ciclo->dispararSeVencido();

        $query = News::query()->where('published', true)->comImagemPublicavel();

        if ($request->has('search')) {
            $query->where('title', 'ilike', '%' . $request->search . '%');
        }

        if ($request->has('relevance_min')) {
            $query->where('relevance_score', '>=', $request->relevance_min);
        }

        $sortBy = $request->get('sort_by', 'published_at');
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'published_at';

        $direction = strtolower((string) $request->get('direction', 'desc'));
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        // per_page opcional (padrão 15, como sempre foi; teto 150): o painel
        // de notícias do front lista até 150 notícias e antes precisava de 10
        // requisições de 15 pra isso — cada uma com a latência do Supabase e
        // enfileirada no servidor. Uma só resolve.
        $porPagina = min(max((int) $request->get('per_page', 15), 1), 150);

        // Desempate por id: sem ele, notícias com o mesmo published_at podiam
        // trocar de lugar entre páginas e aparecer repetidas/omitidas.
        $paginador = $query->orderBy($sortBy, $direction)->orderBy('id', $direction)->paginate($porPagina);

        // Troca cada item pela versão pública (NewsResource) sem alterar o
        // formato do paginador em si — o frontend já espera esse mesmo
        // formato plano (current_page, data, last_page, ...), só que agora
        // sem os campos internos do pipeline (status_resumo, erro_resumo,
        // conteudo_original etc.) vazando pra qualquer consumidor público.
        $paginador->getCollection()->transform(
            fn (News $noticia) => (new NewsResource($noticia))->resolve()
        );

        $resposta = $paginador->toArray();

        // Campo novo e opcional (não altera nenhum campo existente): a notícia
        // principal do momento, escolhida no backend — ver
        // SelecionadorDestaqueNoticia. Só na 1ª página, que é onde o painel usa.
        if ($paginador->currentPage() === 1) {
            $principal = $destaque->selecionar();
            $resposta['destaque'] = $principal ? (new NewsResource($principal))->resolve() : null;
        }

        return response()->json($resposta);
    }

    public function show(News $news)
    {
        // Sem isso, qualquer id (inclusive notícia pendente, em processamento
        // ou reprovada pelo filtro de relevância) era acessível diretamente
        // por URL — bastava adivinhar/incrementar o id — e devolvia o model
        // inteiro, incluindo erro_resumo e o HTML bruto do conteúdo original.
        // O comportamento público correto é o mesmo do index(): só notícia
        // publicada existe pra quem está fora do admin.
        abort_if(!$news->published, 404);

        return new NewsResource($news);
    }
}