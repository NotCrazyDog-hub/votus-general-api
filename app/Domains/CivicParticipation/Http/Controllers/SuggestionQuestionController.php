<?php

namespace App\Domains\CivicParticipation\Http\Controllers;

use App\Domains\CivicParticipation\Models\SuggestionQuestion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SuggestionQuestionController extends Controller
{
    /**
     * Perguntas ativas da pesquisa de sugestões, na ordem em que devem
     * aparecer no formulário público — gerenciadas pelo admin em
     * Admin\SuggestionQuestionController.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SuggestionQuestion::query()
                ->orderBy('order_index')
                ->get(['id', 'text', 'type', 'options', 'required']),
        ]);
    }
}
