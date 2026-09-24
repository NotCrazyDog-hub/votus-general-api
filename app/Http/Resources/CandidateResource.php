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
            'proposal_document_url' => $this->proposal_document_url,
            'election_year' => $this->election_year,
            'running_mates' => CandidateResource::collection($this->whenLoaded('runningMates')),
            'previous_mandates' => LegislatorSummaryResource::collection($this->whenLoaded('previousMandates')),
            'candidacy_history' => $this->whenLoaded('candidacyHistory', function () {
                return $this->candidacyHistory->map(function ($history) {
                    return [
                        'id' => $history->candidacy_external_id,
                        'election_year' => $history->election_year,
                        'round' => $history->round,
                        'state' => $history->state,
                        'office' => $history->office_name,
                        'ballot_number' => $history->ballot_number,

                        'party' => [
                            'acronym' => $history->party_acronym,
                            'name' => $history->party_name,
                        ],

                        'candidacy_status' => $history->candidacy_status,
                        'result_status' => $history->result_status,
                    ];
                });
            }),
        ];
    }
}