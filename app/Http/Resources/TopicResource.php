<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TopicResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'relevance' => $this->whenPivotLoaded('bill_topic', fn () => $this->pivot->relevance),
        ];
    }
}