<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Executive extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'office',
        'level',
        'state',
        'party_acronym',
        'party_name',
        'birth_date',
        'birth_place',
        'occupation',
        'education',
        'biography',
        'photo_path',
        'started_at',
        'ended_at',
        'is_current',
        'source_name',
        'source_url',
        'external_id',
        'raw_data',
        'photo_url',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'started_at' => 'date',
        'ended_at' => 'date',
        'is_current' => 'boolean',
        'raw_data' => 'array',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(ExecutiveAction::class);
    }
}