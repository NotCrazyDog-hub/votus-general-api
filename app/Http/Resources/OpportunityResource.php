<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,

            'opportunity_type' => $this->opportunity_type,
            'title' => $this->title,
            'company' => $this->company,
            'description' => $this->description,

            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            'category' => $this->category,
            'contract_type' => $this->contract_type,
            'contract_time' => $this->contract_time,

            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,

            'external_url' => $this->external_url,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}