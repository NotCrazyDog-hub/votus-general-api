<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SuggestionAnswer;
use App\Models\SuggestionQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuggestionQuestionController extends Controller
{
    private const RULES = [
        'text' => ['required', 'string', 'max:255'],
        'type' => ['required', 'string', 'in:choice,text'],
        'options' => ['required_if:type,choice', 'array', 'min:2'],
        'options.*' => ['string', 'max:255'],
        'required' => ['boolean'],
    ];

    /**
     * Lista as perguntas pra gestão no admin, já com a contagem de respostas
     * por opção nas de múltipla escolha (ex: "Sim, gostei": 3) — é o que
     * alimenta o resumo objetivo no topo da tela de Sugestões.
     */
    public function index(): JsonResponse
    {
        $questions = SuggestionQuestion::query()->orderBy('order_index')->get();

        $contagens = SuggestionAnswer::query()
            ->whereIn('suggestion_question_id', $questions->pluck('id'))
            ->selectRaw('suggestion_question_id, answer, count(*) as total')
            ->groupBy('suggestion_question_id', 'answer')
            ->get()
            ->groupBy('suggestion_question_id');

        $questions->each(function (SuggestionQuestion $question) use ($contagens) {
            $question->setAttribute(
                'stats',
                $question->type === 'choice'
                    ? ($contagens->get($question->id, collect())->pluck('total', 'answer'))
                    : null
            );
        });

        return response()->json(['data' => $questions]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(self::RULES);

        $question = SuggestionQuestion::create([
            'text' => $data['text'],
            'type' => $data['type'],
            'options' => $data['type'] === 'choice' ? $data['options'] : null,
            'required' => $data['required'] ?? true,
            'order_index' => (int) (SuggestionQuestion::max('order_index') ?? 0) + 1,
        ]);

        return response()->json(['data' => $question], 201);
    }

    public function update(Request $request, SuggestionQuestion $suggestionQuestion): JsonResponse
    {
        $data = $request->validate([
            ...self::RULES,
            'type' => ['required', 'string', Rule::in(['choice', 'text'])],
        ]);

        $suggestionQuestion->update([
            'text' => $data['text'],
            'type' => $data['type'],
            'options' => $data['type'] === 'choice' ? $data['options'] : null,
            'required' => $data['required'] ?? true,
        ]);

        return response()->json(['data' => $suggestionQuestion]);
    }

    public function destroy(SuggestionQuestion $suggestionQuestion): JsonResponse
    {
        $suggestionQuestion->delete();

        return response()->json(['message' => 'Pergunta removida.']);
    }
}
