<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidacyHistory extends Model
{
    protected $fillable = [
        'candidate_id',
        'candidacy_external_id',
        'election_year',
        'round',
        'state',
        'office_name',
        'ballot_number',
        'party_acronym',
        'party_name',
        'candidacy_status',
        'result_status',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'election_year' => 'integer',
        'round' => 'integer',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}