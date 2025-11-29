<?php

namespace App\DTOs;

class PaymentData
{
    public function __construct(
        public int $studentId,
        public string $paymentDate,
        public float $subtotal,
        public float $totalAmount,
        public string $paymentMethod,
        public array $paymentDetails,
        public ?float $discount = 0,
        public ?float $lateFee = 0,
        public ?string $notes = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            studentId: (int) self::getSafeValue($data, 'student_id', 0),
            paymentDate: (string) self::getSafeValue($data, 'payment_date', ''),
            subtotal: (float) self::getSafeValue($data, 'subtotal', 0),
            totalAmount: (float) self::getSafeValue($data, 'total_amount', 0),
            paymentMethod: (string) self::getSafeValue($data, 'payment_method', ''),
            paymentDetails: (array) self::getSafeValue($data, 'payment_details', []),
            discount: (float) self::getSafeValue($data, 'discount', 0),
            lateFee: (float) self::getSafeValue($data, 'late_fee', 0),
            notes: self::getSafeValue($data, 'notes')
        );
    }

     // Tambahkan method untuk mendapatkan total amount yang seharusnya
    public function getExpectedTotalAmount(float $monthlyAmount): float
    {
        $expectedSubtotal = count($this->paymentDetails) * $monthlyAmount;
        return $expectedSubtotal - $this->discount + $this->lateFee;
    }

    private static function getSafeValue(array $data, string $key, $default = null)
    {
        $value = $data[$key] ?? $default;

        // Handle empty strings untuk numerik fields
        if (($key === 'subtotal' || $key === 'total_amount' || $key === 'discount' || $key === 'late_fee') && $value === '') {
            return 0;
        }

        return $value;
    }

    // Method untuk validasi amount per bulan
    public function validateMonthlyAmounts(float $monthlyAmount): array
    {
        $errors = [];
        foreach ($this->paymentDetails as $index => $detail) {
            if ($detail['amount'] != $monthlyAmount) {
                $errors[] = [
                    'month' => $detail['month'],
                    'year' => $detail['year'],
                    'amount_provided' => $detail['amount'],
                    'amount_expected' => $monthlyAmount,
                    'difference' => $detail['amount'] - $monthlyAmount
                ];
            }
        }
        return $errors;
    }
}
