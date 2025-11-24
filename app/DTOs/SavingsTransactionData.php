<?php

namespace App\DTOs;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class SavingsTransactionData
{
    public function __construct(
        public int $studentId,
        public string $transactionType,
        public float $amount,
        public string $transactionDate,
        public ?string $notes = null
    ) {}

    public static function fromRequest(array $data): self
    {
        // Validasi field yang required
        $validator = Validator::make($data, [
            'student_id' => 'required|integer|exists:students,id',
            'transaction_type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self(
            studentId: (int) $data['student_id'],
            transactionType: $data['transaction_type'],
            amount: (float) $data['amount'],
            transactionDate: $data['transaction_date'],
            notes: $data['notes'] ?? null
        );
    }
}
