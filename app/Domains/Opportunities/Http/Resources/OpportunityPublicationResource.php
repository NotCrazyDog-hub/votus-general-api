<?php

namespace App\Domains\Opportunities\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityPublicationResource extends JsonResource
{
    /**
     * Converte OpportunityPublication para a resposta JSON da API. Expõe os campos: publication_type,
     * gazette_date, edition, gazette_url. @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'publication_type' => $this->publication_type,
            'gazette_date' => $this->gazette_date?->toDateString(),
            'edition' => $this->edition,
            'gazette_url' => $this->gazette_url,
        ];
    }
}