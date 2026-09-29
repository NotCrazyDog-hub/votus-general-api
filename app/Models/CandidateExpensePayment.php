<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateExpensePayment extends Model
{
    protected $fillable = [
        'candidate_expense_id',
        'expense_external_id',
        'installment_external_id',
        'payment_date',
        'paid_amount',
        'resource_type',
        'document_type',
        'document_number',
        'expense_source',
        'expense_origin',
        'expense_nature',
        'raw_data',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'paid_amount' => 'decimal:2',
        'raw_data' => 'array',
    ];

    public function candidateExpense(): BelongsTo
    {
        return $this->belongsTo(CandidateExpense::class);
    }
}