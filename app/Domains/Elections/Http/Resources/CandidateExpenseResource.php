<?php

namespace App\Domains\Elections\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CandidateExpenseResource extends JsonResource
{
    /**
     * Converte CandidateExpense para a resposta JSON da API. Expõe os campos: id, expense_date,
     * description, amount, supplier_name, supplier_type. @return array<string, mixed>
     */
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
