<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityPublicationResource extends JsonResource
{
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