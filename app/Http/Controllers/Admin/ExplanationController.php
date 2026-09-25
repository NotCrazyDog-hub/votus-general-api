<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Explanations\GerarConteudoExplicacaoJob;
use App\Models\Explanation;
use App\Models\TrustedSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExplanationController extends Controller
{
    // Mesmas três categorias da referência original — o formulário do admin
    // apresenta uma lista fechada, não texto livre, pra manter consistência
    // no conteúdo gerado.
    private const CATEGORIAS = [
        'Órgãos e instituições',
        'Cargos políticos',
        'Eleições e voto',
    ];

    public function index(Request $request): JsonResponse
    {
        $busca = trim((string) $request->query('search', ''));

        $explanations = Explanation::query()
            ->when($busca !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('title', 'ilike', "%{$busca}%")->orWhere('question_title', 'ilike', "%{$busca}%")
            ))
            ->withCount(['sources', 'quizQuestions'])
            ->latest()
            ->paginate(15);

        return response()->json($explanations);
    }

    /**
     * Cria a explicação com status "generating" e despacha a geração pra
     * fila — não chama a Groq nesta mesma requisição (buscar as fontes +
     * gerar o conteúdo pode demorar, e travar o navegador do admin por isso
     * não é aceitável). O admin acompanha o status pela listagem/detalhe, do
     * mesmo jeito que o painel de notícias já faz polling do resumo por IA.
     *
     * O admin informa o(s) link(s) exatos da matéria/página de origem (não
     * só um domínio) — o backend busca e extrai o texto real de lá antes de
     * mandar pra IA, em vez de deixar a IA "inventar" fontes. Cada link
     * precisa pertencer a um domínio já cadastrado como fonte confiável.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'question_title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(self::CATEGORIAS)],
            'source_urls' => ['required', 'array', 'min:1', 'max:5'],
            'source_urls.*' => ['required', 'url', 'max:2048'],
        ]);

        $fontesAtivas = TrustedSource::query()->where('is_active', true)->get()->keyBy(
            fn (TrustedSource $fonte) => $fonte->domain
        );

        if ($fontesAtivas->isEmpty()) {
            return response()->json([
                'message' => 'Nenhuma fonte confiável está cadastrada. Cadastre ao menos uma antes de gerar conteúdo.',
            ], 422);
        }

        $fontesPorUrl = [];

        foreach ($validated['source_urls'] as $url) {
            $dominio = TrustedSource::normalizeDomain($url);
            $fonteConfiavel = $fontesAtivas->get($dominio);

            if (!$fonteConfiavel) {
                return response()->json([
                    'message' => "O link \"{$url}\" não pertence a nenhuma fonte confiável cadastrada e ativa.",
                ], 422);
            }

            $fontesPorUrl[] = ['url' => $url, 'fonte' => $fonteConfiavel];
        }

        $slug = Str::slug($validated['title']);

        if (Explanation::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::lower(Str::random(6));
        }

        $explanation = DB::transaction(function () use ($validated, $slug, $fontesPorUrl) {
            $explanation = Explanation::create([
                'title' => $validated['title'],
                'question_title' => $validated['question_title'],
                'category' => $validated['category'],
                'slug' => $slug,
                'status' => 'generating',
                'content_version' => 1,
            ]);

            foreach ($fontesPorUrl as $item) {
                $explanation->sources()->create([
                    'trusted_source_id' => $item['fonte']->id,
                    'source_name' => $item['fonte']->name,
                    'source_url' => $item['url'],
                    'source_domain' => $item['fonte']->domain,
                ]);
            }

            return $explanation;
        });

        GerarConteudoExplicacaoJob::dispatch($explanation->id);

        // Diferente do resumo de notícias, essa fila ("explanations") não
        // tem nenhum cron externo batendo nela periodicamente — sem essa
        // chamada, o job fica parado pra sempre na tabela jobs, e a
        // explicação nunca sai de "generating". Busca+extração de fontes e
        // uma chamada à Groq são bem mais rápidas que os até 3 minutos do
        // fluxo antigo com n8n, então na prática a geração já termina
        // dentro dessa mesma requisição na maioria dos casos.
        Artisan::call('queue:work', [
            '--queue' => 'explanations',
            '--stop-when-empty' => true,
            '--max-time' => 30,
        ]);

        return response()->json($explanation->fresh()->load('sources'), 201);
    }

    public function show(Explanation $explanation): JsonResponse
    {
        $explanation->load(['sources', 'quizQuestions.options']);

        return response()->json($explanation);
    }

    /**
     * Drena o que já está na fila "explanations", sem criar nada novo — o
     * painel chama isso repetidamente enquanto houver explicação em
     * "generating" (igual ao /admin/news/drain), porque o rate limit da
     * Groq (3/min) ou uma busca de fonte mais lenta podem facilmente passar
     * da janela de 30s que o store() já tenta drenar sozinho.
     */
    public function drain(): JsonResponse
    {
        Artisan::call('queue:work', [
            '--queue' => 'explanations',
            '--stop-when-empty' => true,
            '--max-time' => 20,
        ]);

        return response()->json([
            'status' => 'executado',
            'gerando' => Explanation::where('status', 'generating')->count(),
        ]);
    }

    /**
     * Edição manual do conteúdo já gerado, incluindo as respostas do quiz.
     * Os ids de pergunta/opção precisam pertencer a esta explicação — sem
     * essa validação um id de outra explicação poderia ser editado por
     * engano (ou de propósito).
     */
    public function update(Request $request, Explanation $explanation): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'question_title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(self::CATEGORIAS)],
            'summary' => ['required', 'string'],
            'what_is' => ['required', 'string'],
            'purpose' => ['required', 'string'],
            'practical_role' => ['required', 'string'],
            'why_it_matters' => ['required', 'string'],
            'citizen_impact' => ['required', 'string'],
            'example' => ['required', 'string'],
            'quiz' => ['required', 'array', 'size:5'],
            'quiz.*.id' => ['required', 'integer'],
            'quiz.*.question' => ['required', 'string'],
            'quiz.*.explanation' => ['required', 'string'],
            'quiz.*.correct_option_id' => ['required', 'integer'],
            'quiz.*.options' => ['required', 'array', 'size:4'],
            'quiz.*.options.*.id' => ['required', 'integer'],
            'quiz.*.options.*.text' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($validated, $explanation) {
            $version = $explanation->content_version + 1;

            $explanation->update([
                'title' => $validated['title'],
                'question_title' => $validated['question_title'],
                'category' => $validated['category'],
                'summary' => $validated['summary'],
                'what_is' => $validated['what_is'],
                'purpose' => $validated['purpose'],
                'practical_role' => $validated['practical_role'],
                'why_it_matters' => $validated['why_it_matters'],
                'citizen_impact' => $validated['citizen_impact'],
                'example' => $validated['example'],
                'content_version' => $version,
                // Conteúdo alterado precisa ser revisado de novo antes de
                // voltar a ficar público — mesma regra da referência.
                'status' => 'review',
            ]);

            foreach ($validated['quiz'] as $questionData) {
                $question = $explanation->quizQuestions()->findOrFail($questionData['id']);

                $question->update([
                    'question' => $questionData['question'],
                    'explanation' => $questionData['explanation'],
                    'based_on_content_version' => $version,
                ]);

                foreach ($questionData['options'] as $optionData) {
                    $option = $question->options()->findOrFail($optionData['id']);

                    $option->update([
                        'option_text' => $optionData['text'],
                        'is_correct' => (int) $optionData['id'] === (int) $questionData['correct_option_id'],
                    ]);
                }
            }
        });

        $explanation->load(['sources', 'quizQuestions.options']);

        return response()->json($explanation);
    }

    public function publish(Explanation $explanation): JsonResponse
    {
        $explanation->load(['sources', 'quizQuestions.options']);

        if ($explanation->sources->isEmpty()) {
            return response()->json(['message' => 'A publicação precisa possuir fontes.'], 422);
        }

        if ($explanation->quizQuestions->count() !== 5) {
            return response()->json(['message' => 'O quiz precisa possuir 5 perguntas.'], 422);
        }

        foreach ($explanation->quizQuestions as $question) {
            if ($question->options->count() !== 4) {
                return response()->json(['message' => 'Cada pergunta precisa possuir 4 alternativas.'], 422);
            }

            if ($question->options->where('is_correct', true)->count() !== 1) {
                return response()->json(['message' => 'Cada pergunta deve possuir uma resposta correta.'], 422);
            }
        }

        $explanation->update(['status' => 'published', 'published_at' => now()]);

        return response()->json($explanation);
    }

    public function unpublish(Explanation $explanation): JsonResponse
    {
        $explanation->update(['status' => 'review', 'published_at' => null]);

        return response()->json($explanation);
    }

    public function destroy(Explanation $explanation): JsonResponse
    {
        // As FKs de explanation_sources/quiz_questions/quiz_options já são
        // ON DELETE CASCADE — não precisa apagar filho por filho na mão.
        $explanation->delete();

        return response()->json(['message' => 'Explicação removida.']);
    }
}
