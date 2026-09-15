<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitteeTopic extends Model
{
    protected $table = 'committee_topic';

    protected $fillable = [
        'committee_id',
        'topic_id',
        'match_score',
        'match_method',
        'reviewed',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed' => 'boolean',
        'reviewed_at' => 'datetime',
        'match_score' => 'decimal:4',
    ];

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}