<?php

namespace App\Domains\Legislatures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TopicResource extends JsonResource
{
    /**
     * Converte Topic para a resposta JSON da API. Expõe os campos: id, name, relevance. @return
     * array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'relevance' => $this->whenPivotLoaded('bill_topic', fn () => $this->pivot->relevance),
        ];
    }
}