<?php

namespace App\Models;

use App\Enums\CandidateOffice;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $fillable = [
        'external_id',
        'ballot_number',
        'round',
        'coverage_scope',
        'state',
        'office_code',
        'office_name',
        'civil_name',
        'ballot_name',
        'cpf',
        'party_acronym',
        'party_name',
        'education_level',
        'occupation',
        'race_color',
        'election_year',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'round' => 'integer',
        'election_year' => 'integer',
    ];
}