<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutiveAction extends Model
{
    protected $fillable = [
        'executive_id',
        'title',
        'summary',
        'action_type',
        'occurred_at',
        'published_at',
        'location',
        'source_name',
        'source_type',
        'source_url',
        'entities',
        'raw_data',
        'relevance_score',
        'analysis_status',
        'analyzed_at',
    ];

    protected $casts = [
        'occurred_at' => 'date',
        'published_at' => 'datetime',
        'analyzed_at' => 'datetime',
        'entities' => 'array',
        'raw_data' => 'array',
        'relevance_score' => 'decimal:2',
    ];

    public function executive(): BelongsTo
    {
        return $this->belongsTo(Executive::class);
    }
}