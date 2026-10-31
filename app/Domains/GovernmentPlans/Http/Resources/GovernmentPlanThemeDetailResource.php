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

     * Converte GovernmentPlanThemeDetail para a resposta JSON da API. Expõe os campos: theme, candidates, comparison.

     *

     * @return array<string, mixed>

     */

    public function toArray(Request $request): array
    {
        return [
            'theme' => ['slug' => $this->slug, 'name' => $this->name],
            'candidates' => $this->proposals
                ->groupBy('candidate_id')
                // use ($request): sem ele, $request fica indefinido dentro do
                // map e o detalhe do tema respondia 500.
                ->map(function ($proposals) use ($request) {
                    $candidate = $proposals->first()->candidate;

                    return [
                        ...(new GovernmentPlanCandidateResource($candidate))->resolve($request),
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