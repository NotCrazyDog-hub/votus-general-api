<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TopicResource;

class BillResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'chamber' => $this->chamber,
            'type' => $this->type,
            'summary' => $this->summary,
            'presented_at' => $this->presented_at,
            'status' => [
                'situacao' => $this->status_situacao,
                'sigla' => $this->status_sigla,
                'tramitando' => $this->status_tramitando,
                'checked_at' => $this->status_checked_at,
            ],
            'topics' => TopicResource::collection($this->whenLoaded('topics')),
        ];
    }
}