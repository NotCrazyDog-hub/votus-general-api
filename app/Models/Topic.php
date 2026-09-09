<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $fillable = ['external_id', 'chamber', 'name'];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function bills()
    {
        return $this->belongsToMany(Bill::class, 'bill_topic')
            ->withPivot('relevance')
            ->withTimestamps();
    }
}