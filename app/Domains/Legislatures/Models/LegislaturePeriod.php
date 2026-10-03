<?php

namespace App\Domains\Legislatures\Models;

use Illuminate\Database\Eloquent\Model;

class LegislaturePeriod extends Model
{
    protected $fillable = ['legislature_number', 'chamber', 'starts_at', 'ends_at'];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];
}