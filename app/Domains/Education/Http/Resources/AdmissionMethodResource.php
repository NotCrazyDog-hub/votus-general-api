<?php

namespace App\Domains\Education\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionMethodResource extends JsonResource
{
    /**
     * Converte AdmissionMethod para a resposta JSON da API. Expõe os campos: type, name, description,
     * official_url, verified_at. @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'official_url' => $this->official_url,
            'verified_at' => $this->verified_at?->toDateString(),
        ];
    }
}