<?php

namespace App\Jobs\Explanations;

use App\Models\Explanation;
use App\Services\Explanations\ExplanationSourceFetcher;
use App\Services\Explanations\GroqExplanationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class GerarConteudoExplicacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    public array $backoff = [30];

    public function __construct(public int $explanationId)
    {
        $this->onQueue('explanations');
    }

    public function middleware(): array
    {
        // Reaproveita a mesma cota da Groq usada pelo resumo de notícias —
        // conservador de propósito, já que essa chamada tem um material de
        // referência maior (várias fontes) e um max_tokens de saída alto.
        return [new RateLimited('explicacao-quiz')];
    }

    public function handle(ExplanationSourceFetcher $fetcher, GroqExplanationService $groq): void
    {
        $explanation = Explanation::find($this->explanationId);

        if (!$explanation || $explanation->status !== 'generating') {
            return;
        }

        $sources = $explanation->sources()->get();

        if ($sources->isEmpty()) {
            $explanation->update([
                'status' => 'failed',
                'generation_error' => 'Nenhuma fonte foi informada para esta explicação.',
            ]);

            return;
        }

        try {
            $material = $this->buscarMaterialDeReferencia($sources, $fetcher);

            $resultado = $groq->gerar($explanation->question_title, $explanation->category, $material);

            $this->validarQuiz($resultado);

            DB::transaction(function () use ($explanation, $resultado) {
                $this->salvarConteudoGerado($explanation, $resultado);
            });
        } catch (Throwable $e) {
            $explanation->update([
                'status' => 'failed',
                'generation_error' => str($e->getMessage())->limit(500)->toString(),
            ]);
        }
    }

    private function buscarMaterialDeReferencia($sources, ExplanationSourceFetcher $fetcher): string
    {
        $blocos = [];

        foreach ($sources as $source) {
            $texto = $fetcher->buscarTexto($source->source_url);
            $blocos[] = "Fonte: {$source->source_name} ({$source->source_domain})\n{$texto}";
        }

        return implode("\n\n---\n\n", $blocos);
    }

    private function validarQuiz(array $resultado): void
    {
        if (!isset($resultado['explanation']) || !is_array($resultado['explanation'])) {
            throw new RuntimeException('A explicação não foi retornada.');
        }

        if (!isset($resultado['quiz']) || count($resultado['quiz']) !== 5) {
            throw new RuntimeException('O quiz deve possuir 5 perguntas.');
        }

        foreach ($resultado['quiz'] as $question) {
            if (!isset($question['options']) || count($question['options']) !== 4) {
                throw new RuntimeException('Cada pergunta deve possuir 4 alternativas.');
            }

            $corretas = collect($question['options'])->where('correct', true)->count();

            if ($corretas !== 1) {
                throw new RuntimeException('Cada pergunta deve possuir exatamente uma resposta correta.');
            }
        }
    }

    private function salvarConteudoGerado(Explanation $explanation, array $resultado): void
    {
        $generated = $resultado['explanation'];

        $explanation->update([
            'summary' => $generated['summary'] ?? null,
            'what_is' => $generated['what_is'] ?? null,
            'purpose' => $generated['purpose'] ?? null,
            'practical_role' => $generated['practical_role'] ?? null,
            'why_it_matters' => $generated['why_it_matters'] ?? null,
            'citizen_impact' => $generated['citizen_impact'] ?? null,
            'example' => $generated['example'] ?? null,
            'status' => 'review',
            'generation_error' => null,
        ]);

        foreach ($resultado['quiz'] as $questionIndex => $questionData) {
            $question = $explanation->quizQuestions()->create([
                'question' => $questionData['question'],
                'explanation' => $questionData['explanation'] ?? null,
                'position' => $questionIndex + 1,
                'based_on_content_version' => $explanation->content_version,
            ]);

            foreach ($questionData['options'] as $optionIndex => $optionData) {
                $question->options()->create([
                    'option_text' => $optionData['text'],
                    'is_correct' => (bool) ($optionData['correct'] ?? false),
                    'position' => $optionIndex + 1,
                ]);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Explanation::where('id', $this->explanationId)
            ->where('status', 'generating')
            ->update([
                'status' => 'failed',
                'generation_error' => str($exception->getMessage())->limit(500)->toString(),
            ]);
    }
}
