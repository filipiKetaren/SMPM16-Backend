<?php

namespace App\Http\Resources\Finance\Savings;

use Illuminate\Http\Resources\Json\JsonResource;

class SavingsTransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'student_id' => $this->student_id,
            'transaction_type' => $this->transaction_type,
            'amount' => (float) $this->amount,
            'balance_before' => (float) $this->balance_before,
            'balance_after' => (float) $this->balance_after,
            'transaction_date' => $this->transaction_date->format('Y-m-d'),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'student' => [
                'id' => $this->student->id,
                'nis' => $this->student->nis,
                'full_name' => $this->student->full_name,
                'class' => $this->student->class->name,
            ],
            'creator' => [
                'id' => $this->creator->id,
                'username' => $this->creator->username,
                'full_name' => $this->creator->full_name,
            ],
        ];
    }
}
