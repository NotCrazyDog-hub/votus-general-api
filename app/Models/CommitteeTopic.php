<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeTopic extends Model
{
    protected $table = 'committee_topic';

    protected $fillable = [
        'reviewed_source',
        'ai_confidence',
        'ai_reasoning',
    ];

    protected $casts = [
        'ai_confidence' => 'decimal:4',
    ];
}
