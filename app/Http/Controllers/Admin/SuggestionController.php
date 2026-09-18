<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Suggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $busca = trim((string) $request->query('search', ''));

        return response()->json(
            Suggestion::query()
                ->with('answers.question:id,text')
                ->when($busca !== '', fn ($query) => $query
                    ->where(fn ($q) => $q
                        ->where('name', 'like', "%{$busca}%")
                        ->orWhere('message', 'like', "%{$busca}%")
                        ->orWhereHas('answers', fn ($a) => $a->where('answer', 'like', "%{$busca}%"))))
                ->orderByDesc('created_at')
                ->paginate(15)
        );
    }

    /**
     * Cadastro manual de uma sugestão pelo próprio admin — pra registrar
     * sugestões recebidas por outros canais (ex: verbalmente, redes sociais)
     * no mesmo lugar das enviadas pelo formulário público.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $suggestion = Suggestion::create($data);

        return response()->json(['data' => $suggestion], 201);
    }

    /**
     * As respostas (suggestion_answers) têm FK com cascadeOnDelete pra
     * suggestion_id — apagar aqui já limpa tudo, sem deixar resposta órfã.
     */
    public function destroy(Suggestion $suggestion): JsonResponse
    {
        $suggestion->delete();

        return response()->json(['message' => 'Sugestão removida.']);
    }
}
