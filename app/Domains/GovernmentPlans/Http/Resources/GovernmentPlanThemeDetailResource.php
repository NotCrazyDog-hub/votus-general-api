<?php

namespace App\Domains\GovernmentPlans\Http\Resources;

use App\Domains\GovernmentPlans\Models\GovernmentPlanComparison;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tema com as propostas agrupadas por candidato e a comparação (se houver).
 * Espera o tema com proposals.candidate carregados.
 */
class GovernmentPlanThemeDetailResource extends JsonResource
{
    public function __construct($resource, private readonly ?GovernmentPlanComparison $comparison = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'theme' => ['slug' => $this->slug, 'name' => $this->name],
            'candidates' => $this->proposals
                ->groupBy('candidate_id')
                ->map(function ($proposals) {
                    $candidate = $proposals->first()->candidate;

                    return [
                        'id' => $candidate->id,
                        'external_id' => $candidate->external_id,
                        'name' => $candidate->ballot_name,
                        'party' => $candidate->party_acronym,
                        'proposals' => GovernmentPlanProposalResource::collection($proposals->values()),
                    ];
                })
                ->values(),
            'comparison' => $this->comparison
                ? new GovernmentPlanComparisonResource($this->comparison)
                : null,
        ];
    }
}