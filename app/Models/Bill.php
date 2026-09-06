<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    protected $fillable = [
        'external_id',
        'chamber',
        'legislator_id',
        'type',
        'summary',
        'presented_at',
        'raw_data',
        'status_situacao', 
        'status_sigla', 
        'status_tramitando', 
        'status_checked_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'presented_at' => 'date',
    ];

    public function legislators(): BelongsToMany
    {
        return $this->belongsToMany(Legislator::class, 'bill_legislator');
    }

    public function topics()
    {
        return $this->belongsToMany(Topic::class, 'bill_topic')
            ->withPivot('relevance')
            ->withTimestamps();
    }

    public function tramitations(): HasMany
    {
        return $this->hasMany(BillTramitation::class);
    }
}