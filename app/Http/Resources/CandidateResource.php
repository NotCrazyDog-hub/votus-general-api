<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CandidateResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->external_id,
            'ballot_number' => $this->ballot_number,
            'round' => $this->round,
            'state' => $this->state,
            'office' => $this->office_name,
            'civil_name' => $this->civil_name,
            'ballot_name' => $this->ballot_name,
            'party' => [
                'acronym' => $this->party_acronym,
                'name' => $this->party_name,
            ],
            'education_level' => $this->education_level,
            'occupation' => $this->occupation,
            'race_color' => $this->race_color,
            'photo_url' => $this->photo_url,
            'election_year' => $this->election_year,
            'running_mates' => CandidateResource::collection($this->whenLoaded('runningMates')),
        ];
    }
}