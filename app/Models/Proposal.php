<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proposal extends Model
{
    protected $fillable = [
        'title',
        'content',
        'category',
        'author',
        'status',
    ];

    protected $casts = [
        'status' => ProposalStatus::class,
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(ProposalVote::class);
    }
}
