<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityPublication extends Model
{
    protected $fillable = [
        'public_opportunity_id',
        'publication_key',
        'publication_type',
        'territory_id',
        'gazette_date',
        'edition',
        'gazette_url',
        'txt_url',
        'source_excerpt',
    ];


    protected function casts(): array
    {
        return [
            'gazette_date' => 'date',
        ];
    }


    public function publicOpportunity(): BelongsTo
    {
        return $this->belongsTo(
            PublicOpportunity::class
        );
    }
}