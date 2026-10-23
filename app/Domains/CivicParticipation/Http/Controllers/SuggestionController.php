<?php

namespace App\Domains\CivicParticipation\Http\Controllers;

use App\Domains\CivicParticipation\Models\Suggestion;
use App\Domains\CivicParticipation\Models\SuggestionQuestion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuggestionController extends Controller
{
    /**
     * Envio da pesquisa pública de sugestões — uma resposta por pergunta
     * dinâmica (ver SuggestionQuestionController), não mais um texto único.
     * Toda pergunta obrigatória precisa de resposta não vazia.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.suggestion_question_id' => ['required', 'integer', 'exists:suggestion_questions,id'],
            'answers.*.answer' => ['nullable', 'string', 'max:5000'],
        ]);

        // Todas as obrigatórias, não só as que vieram no envio: antes, omitir
        // uma pergunta obrigatória do array pulava a verificação (o form do
        // site já exige, mas a API aceitava envios diretos sem ela).
        $requiredQuestions = SuggestionQuestion::where('required', true)->get();

        foreach ($requiredQuestions as $question) {
            $resposta = collect($data['answers'])
                ->firstWhere('suggestion_question_id', $question->id);

            if (!$resposta || trim((string) ($resposta['answer'] ?? '')) === '') {
                return response()->json([
                    'message' => "A pergunta \"{$question->text}\" é obrigatória.",
                ], 422);
            }
        }

        $suggestion = DB::transaction(function () use ($data) {
            $suggestion = Suggestion::create([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
            ]);

            foreach ($data['answers'] as $resposta) {
                $texto = trim((string) ($resposta['answer'] ?? ''));

                if ($texto === '') {
                    continue;
                }

                $suggestion->answers()->create([
                    'suggestion_question_id' => $resposta['suggestion_question_id'],
                    'answer' => $texto,
                ]);
            }

            return $suggestion;
        });

        return response()->json(['message' => 'Sugestão enviada com sucesso.', 'data' => ['id' => $suggestion->id]], 201);
    }
}
