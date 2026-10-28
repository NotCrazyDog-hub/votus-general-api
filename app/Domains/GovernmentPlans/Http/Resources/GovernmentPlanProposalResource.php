<?php

namespace App\Domains\GovernmentPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GovernmentPlanProposalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'source_excerpt' => $this->source_excerpt,
            'source_reference' => $this->source_reference,
            'source_url' => $this->source_url,
            // 'literal' (texto copiado da fonte) ou 'ai' (resumo da IA).
            'extraction' => $this->extractionMethod(),
        ];
    }
}