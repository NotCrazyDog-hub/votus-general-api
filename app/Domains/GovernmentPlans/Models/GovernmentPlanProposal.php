<?php

namespace App\Domains\GovernmentPlans\Models;

use App\Domains\Elections\Models\Candidate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernmentPlanProposal extends Model
{
    protected $fillable = [
        'candidate_id',
        'government_plan_theme_id',
        'title',
        'description',
        'source_excerpt',
        'source_reference',
        'source_url',
        'fingerprint',
        'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    /**
     * Propostas em uso. Uma proposta substituída por outra versão (ex.: o
     * resumo da IA trocado pelo texto literal do site) NÃO é apagada: recebe
     * metadata.superseded_at e só deixa de aparecer na API.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('metadata->superseded_at');
    }

    /** 'literal' = título e texto copiados da fonte; 'ai' = resumo gerado pela IA. */
    public function extractionMethod(): string
    {
        return $this->metadata['extraction'] ?? 'ai';
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(GovernmentPlanTheme::class, 'government_plan_theme_id');
    }
}
