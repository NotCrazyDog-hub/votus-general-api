<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends Model
{
    protected $fillable = [
        'university_id',
        'name',
        'ibge_city_code',
        'city',
        'normalized_city',
        'state',
        'region',
        'address',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }
}