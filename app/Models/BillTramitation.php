<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillTramitation extends Model
{
    protected $fillable = [
        'bill_id',
        'event_date',
        'sequence',
        'committee_code',
        'action_description',
        'status_description',
        'status_code',
        'dispatch_text',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}