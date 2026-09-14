<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeTopic extends Model
{
    protected $table = 'committee_topic';

    protected $fillable = [
        'committee_id',
        'topic_id',
        'match_score',
        'match_method',
        'reviewed',
        'reviewed_source',
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

    public function committee()
    {
        return $this->belongsTo(Committee::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}
