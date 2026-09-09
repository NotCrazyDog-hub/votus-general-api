<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionMethod extends Model
{
    protected $fillable = [
        'university_id',
        'type',
        'name',
        'description',
        'official_url',
        'active',
        'verified_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }
}