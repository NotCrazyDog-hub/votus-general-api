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
    ];

    protected $casts = [
        'ai_confidence' => 'decimal:4',
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