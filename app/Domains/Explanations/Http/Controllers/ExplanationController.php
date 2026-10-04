<?php

namespace App\Domains\Explanations\Http\Controllers;

use App\Domains\Explanations\Http\Resources\ExplanationResource;
use App\Domains\Explanations\Models\Explanation;
use App\Http\Controllers\Controller;

class ExplanationController extends Controller
{
    public function index()
    {
        $paginador = Explanation::query()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->paginate(12);

        $paginador->getCollection()->transform(
            fn (Explanation $explanation) => (new ExplanationResource($explanation))->resolve()
        );

        return response()->json($paginador);
    }

    public function show(Explanation $explanation)
    {
        // Mesma regra do NewsController::show(): só notícia/conteúdo
        // publicado é visível fora do admin, senão 404 — sem isso, dava pra
        // acessar por id qualquer explicação ainda em geração/revisão.
        abort_if($explanation->status !== 'published', 404);

        $explanation->load(['sources', 'quizQuestions.options']);

        return new ExplanationResource($explanation);
    }
}
