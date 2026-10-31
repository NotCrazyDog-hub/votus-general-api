<?php

namespace App\Domains\GovernmentPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GovernmentPlanComparisonResource extends JsonResource
{
    /**
     * Expõe o resumo da comparação e os dados dos dois candidatos. Os campos
     * de listas e resumos individuais usam arrays vazios quando o banco contém
     * null, mantendo uma estrutura previsível para o consumidor da API.
     */
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->summary,
            'similarities' => $this->similarities ?? [],
            'differences' => $this->differences ?? [],
            'candidate_summaries' => $this->candidate_summaries ?? [],
            'candidate_one_id' => $this->candidate_one_id,
            'candidate_two_id' => $this->candidate_two_id,
        ];
    }
}