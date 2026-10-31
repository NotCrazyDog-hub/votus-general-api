<?php

namespace App\Domains\CivicParticipation\Models;

use App\Domains\CivicParticipation\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proposal extends Model
{
    protected $fillable = [
        'title',
        'content',
        'author',
        'status',
    ];

    protected $casts = [
        'status' => ProposalStatus::class,
    ];

    /**
     * Relação um-para-muitos com o modelo `ProposalVote`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(ProposalVote::class);
    }

    /**
     * Relação um-para-muitos com o modelo `ProposalComment`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ProposalComment::class);
    }

    /**
     * Relação muitos-para-muitos entre Proposal e Category, mantida pela tabela intermediária do Eloquent.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }
}
