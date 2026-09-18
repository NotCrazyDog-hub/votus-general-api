<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LegislatorSummaryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->external_id,
            'chamber' => $this->chamber,
            'parliamentary_name' => $this->parliamentary_name,
            'party' => $this->party,
            'state' => $this->state,
            'status' => $this->status,
        ];
    }
}