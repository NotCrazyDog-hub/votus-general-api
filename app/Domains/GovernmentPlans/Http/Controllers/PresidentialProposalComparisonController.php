<?php

namespace App\Domains\GovernmentPlans\Http\Controllers;

use App\Domains\GovernmentPlans\Http\Resources\GovernmentPlanOverviewResource;
use App\Domains\GovernmentPlans\Http\Resources\GovernmentPlanThemeDetailResource;
use App\Domains\GovernmentPlans\Http\Resources\GovernmentPlanThemeResource;
use App\Domains\GovernmentPlans\Services\PresidentialProposalComparisonService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PresidentialProposalComparisonController extends Controller
{
    public function __construct(private readonly PresidentialProposalComparisonService $service)
    {
    }

    /**
     * Tudo de uma vez (candidatos, temas e propostas, ~160 itens) para a
     * busca por palavra-chave e a visão geral do front, sem uma requisição
     * por tema. As comparações por tema continuam em show().
     */
    public function proposals(): GovernmentPlanOverviewResource
    {
        return new GovernmentPlanOverviewResource(
            $this->service->allProposals(),
            $this->service->candidates(),
        );
    }

    /** Lista temas que têm ao menos uma proposta ativa para a comparação. */
    public function themes(): AnonymousResourceCollection
    {
        return GovernmentPlanThemeResource::collection($this->service->themes());
    }

    /**
     * Valida IDs opcionais de candidatos, carrega o tema e retorna suas propostas
     * junto da comparação existente para o par selecionado, se houver.
     */
    public function show(Request $request, string $theme): GovernmentPlanThemeDetailResource
    {
        $data = $request->validate([
            'candidate_one_id' => ['nullable', 'integer', 'exists:candidates,id'],
            'candidate_two_id' => ['nullable', 'integer', 'different:candidate_one_id', 'exists:candidates,id'],
        ]);

        $theme = $this->service->byTheme($theme);
        $comparison = $this->service->comparisonFor(
            $theme,
            $data['candidate_one_id'] ?? null,
            $data['candidate_two_id'] ?? null,
        );

        return new GovernmentPlanThemeDetailResource($theme, $comparison);
    }
}