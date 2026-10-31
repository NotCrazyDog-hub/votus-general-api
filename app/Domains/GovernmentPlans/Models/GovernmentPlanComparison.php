<?php

namespace App\Domains\GovernmentPlans\Models;

use App\Domains\Elections\Models\Candidate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernmentPlanComparison extends Model
{
    protected $fillable = [
        'government_plan_theme_id',
        'candidate_one_id',
        'candidate_two_id',
        'summary',
        'similarities',
        'differences',
        'candidate_summaries',
    ];

    protected $casts = [
        'similarities' => 'array',
        'differences' => 'array',
        'candidate_summaries' => 'array',
    ];

    /**
     * Cada registro guarda a referência para um único `GovernmentPlanTheme`; vários registros podem compartilhar esse mesmo registro.
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(GovernmentPlanTheme::class, 'government_plan_theme_id');
    }

    /**
     * Cada registro guarda a referência para um único `Candidate`; vários registros podem compartilhar esse mesmo registro.
     */
    public function candidateOne(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'candidate_one_id');
    }

    /**
     * Cada registro guarda a referência para um único `Candidate`; vários registros podem compartilhar esse mesmo registro.
     */
    public function candidateTwo(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'candidate_two_id');
    }
}
