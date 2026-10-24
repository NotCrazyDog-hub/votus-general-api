<?php

namespace App\Domains\GovernmentPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Visão geral para a busca por palavra-chave do front: candidatos, temas e
 * todas as propostas em uma única resposta. O recurso em si é a coleção de
 * propostas (com theme carregado); os candidatos entram pelo construtor.
 */
class GovernmentPlanOverviewResource extends JsonResource
{
    public function __construct($proposals, private readonly iterable $candidates)
    {
        parent::__construct($proposals);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $themes = $this->resource->pluck('theme')->unique('id')->sortBy('name')->values();

        return [
            'candidates' => GovernmentPlanOverviewCandidateResource::collection(collect($this->candidates)->values()),
            'themes' => GovernmentPlanThemeResource::collection($themes),
            'proposals' => GovernmentPlanOverviewProposalResource::collection($this->resource->values()),
        ];
    }
}