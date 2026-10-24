<?php

namespace App\Domains\GovernmentPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Proposta da visão geral: vem solta (sem agrupar por candidato/tema), então
 * carrega os ids para o front relacionar. Espera a relação theme carregada.
 */
class GovernmentPlanOverviewProposalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'candidate_id' => $this->candidate_id,
            'theme_slug' => $this->theme->slug,
            'title' => $this->title,
            'description' => $this->description,
            'source_excerpt' => $this->source_excerpt,
            'source_reference' => $this->source_reference,
            'source_url' => $this->source_url,
        ];
    }
}