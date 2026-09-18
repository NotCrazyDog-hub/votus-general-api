<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExplanationResource extends JsonResource
{
    // Igual ao NewsResource: o frontend consome os campos direto na raiz,
    // sem wrapper "data".
    public static $wrap = null;

    /**
     * Nunca expõe generation_error nem content_version — são detalhes
     * internos do pipeline de geração, sem utilidade pro público. O quiz
     * inclui is_correct nas opções de propósito: a correção é feita no
     * cliente (sem endpoint de submissão), igual à referência original.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'question_title' => $this->question_title,
            'category' => $this->category,
            'summary' => $this->summary,
            'what_is' => $this->what_is,
            'purpose' => $this->purpose,
            'practical_role' => $this->practical_role,
            'why_it_matters' => $this->why_it_matters,
            'citizen_impact' => $this->citizen_impact,
            'example' => $this->example,
            'published_at' => $this->published_at,
            'sources' => $this->whenLoaded('sources', fn () => $this->sources->map(fn ($source) => [
                'id' => $source->id,
                'name' => $source->source_name,
                'url' => $source->source_url,
                'domain' => $source->source_domain,
            ])),
            'quiz_questions' => $this->whenLoaded('quizQuestions', fn () => $this->quizQuestions->map(fn ($question) => [
                'id' => $question->id,
                'question' => $question->question,
                'explanation' => $question->explanation,
                'position' => $question->position,
                'options' => $question->options->map(fn ($option) => [
                    'id' => $option->id,
                    'text' => $option->option_text,
                    'is_correct' => $option->is_correct,
                    'position' => $option->position,
                ]),
            ])),
        ];
    }
}
