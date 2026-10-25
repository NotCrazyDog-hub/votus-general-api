<?php

namespace App\Domains\GovernmentPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GovernmentPlanComparisonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
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