<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $fillable = [

        'source',

        'external_id',

        'opportunity_type',

        'title',

        'company',

        'description',

        'location',

        'location_area',

        'latitude',

        'longitude',

        'category',

        'contract_type',

        'contract_time',

        'salary_min',

        'salary_max',

        'external_url',

        'published_at',

        'last_seen_at',

        'is_active',
    ];


    protected $casts = [

        'location_area' => 'array',

        'salary_min' => 'decimal:2',

        'salary_max' => 'decimal:2',

        'published_at' => 'datetime',

        'last_seen_at' => 'datetime',

        'is_active' => 'boolean',
    ];
}