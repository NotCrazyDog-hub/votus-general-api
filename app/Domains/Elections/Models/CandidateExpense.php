<?php

namespace App\Domains\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateExpense extends Model
{
    protected $fillable = [
        'candidate_id',
        'expense_external_id',
        'expense_date',
        'description',
        'amount',
        'supplier_document',
        'supplier_name',
        'supplier_type',
        'document_type',
        'document_number',
        'expense_origin',
        'accounting_report_date',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'expense_date' => 'date',
        'accounting_report_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function payments()
    {
        return $this->hasMany(CandidateExpensePayment::class);
    }
}