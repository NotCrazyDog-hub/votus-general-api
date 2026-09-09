<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\LegislatorStatus;
use App\Enums\ElectoralStatus;

class Legislator extends Model
{
    protected $fillable = [
        'external_id',
        'chamber',
        'civil_name',
        'parliamentary_name',
        'photo_url',
        'party',
        'state',
        'legislature',
        'electoral_status',
        'status',
        'phone',
        'email',
        'official_website',
        'social_media',
        'raw_data',
        'effectiveness_total_bills',
        'effectiveness_advanced_bills',
        'effectiveness_rate',
        'effectiveness_wilson_lower',
        'effectiveness_calculated_at',
        'mandate_started_at', 
        'productivity_bills_per_year', 
        'productivity_calculated_at',
    ];

    protected $casts = [
        'social_media' => 'array',
        'raw_data' => 'array',
        'status' => LegislatorStatus::class,
        'electoral_status' => ElectoralStatus::class,
    ];

    protected $hidden = [
        'raw_data',
        'created_at',
        'updated_at'
    ];

    public function committees(): BelongsToMany
    {
        return $this->belongsToMany(Committee::class, 'committee_legislator')
            ->withPivot(['role', 'start_date', 'end_date']);
    }
    public function bills(): BelongsToMany
    {
        return $this->belongsToMany(Bill::class, 'bill_legislator');
    }

    public function professions(): BelongsToMany
    {
        return $this->belongsToMany(Profession::class)
            ->withPivot(['source', 'original_name', 'is_primary', 'registered_at'])
            ->withTimestamps();
    }
}
