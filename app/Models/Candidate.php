<?php

namespace App\Models;

use App\Enums\CandidateOffice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use App\Models\Legislator;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'running_mate_of_id',
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
        'photo_path',
        'proposal_document_path',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'round' => 'integer',
        'election_year' => 'integer',
    ];

    protected $appends = [
        'photo_url',
        'proposal_document_url',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::url($this->photo_path) : null;
    }

    public function getProposalDocumentUrlAttribute(): ?string
    {
        return $this->proposal_document_path
            ? Storage::disk('supabase')->url($this->proposal_document_path)
            : null;
    }

    public function mainCandidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'running_mate_of_id');
    }

    public function runningMateOf(): BelongsTo
    {
        return $this->mainCandidate();
    }

    public function runningMates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'running_mate_of_id');
    }

    public function scopeTitulares($query)
    {
        return $query->whereNull('running_mate_of_id');
    }

    public function scopeRunningMates($query)
    {
        return $query->whereNotNull('running_mate_of_id');
    }

    public function previousMandates()
    {
        return $this->hasMany(Legislator::class, 'cpf', 'cpf');
    }
}