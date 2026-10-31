<?php

namespace App\Domains\GovernmentPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GovernmentPlanCandidateResource extends JsonResource
{
    /**
     * Converte GovernmentPlanCandidate para a resposta JSON da API. Expõe os campos: id, external_id,
     * name, party. @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->ballot_name,
            'party' => $this->party_acronym,
        ];
    }
}