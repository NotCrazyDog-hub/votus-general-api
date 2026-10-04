<?php

namespace App\Domains\Elections\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CandidateExpenseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'expense_date' => $this->expense_date,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'supplier_name' => $this->supplier_name,
            'supplier_type' => $this->supplier_type,
        ];
    }
}
