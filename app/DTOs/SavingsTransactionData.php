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
        // Validasi field yang required dengan safe type casting
        $validator = Validator::make($data, [
            'student_id' => 'required|integer|exists:students,id',
            'transaction_type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string|max:500'
        ], [
            'student_id.required' => 'Siswa harus dipilih',
            'student_id.exists' => 'Siswa tidak ditemukan',
            'transaction_type.required' => 'Jenis transaksi harus dipilih',
            'transaction_type.in' => 'Jenis transaksi harus deposit atau withdrawal',
            'amount.required' => 'Jumlah transaksi harus diisi',
            'amount.numeric' => 'Jumlah transaksi harus berupa angka',
            'amount.min' => 'Jumlah transaksi harus lebih dari 0',
            'transaction_date.required' => 'Tanggal transaksi harus diisi',
            'transaction_date.date' => 'Format tanggal transaksi tidak valid',
            'notes.max' => 'Catatan maksimal 500 karakter'
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self(
            studentId: (int) $data['student_id'],
            transactionType: (string) $data['transaction_type'],
            amount: (float) $data['amount'],
            transactionDate: (string) $data['transaction_date'],
            notes: isset($data['notes']) ? (string) $data['notes'] : null
        );
    }

    /**
     * Safe method untuk membuat instance tanpa validation
     * Digunakan untuk testing atau cases tertentu
     */
    public static function fromArray(array $data): self
    {
        return new self(
            studentId: (int) ($data['student_id'] ?? 0),
            transactionType: (string) ($data['transaction_type'] ?? ''),
            amount: (float) ($data['amount'] ?? 0),
            transactionDate: (string) ($data['transaction_date'] ?? ''),
            notes: isset($data['notes']) ? (string) $data['notes'] : null
        );
    }
}
