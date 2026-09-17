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
        'ai_confidence',
        'ai_reasoning',
        'reviewed_at',
    ];

    protected $casts = [
        'ai_confidence' => 'decimal:4',
        'match_score' => 'decimal:4',
        'reviewed' => 'boolean',
        'reviewed_at' => 'datetime',
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
