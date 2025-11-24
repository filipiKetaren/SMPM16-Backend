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
            studentId: $data['student_id'],
            paymentDate: $data['payment_date'],
            subtotal: $data['subtotal'],
            totalAmount: $data['total_amount'],
            paymentMethod: $data['payment_method'],
            paymentDetails: $data['payment_details'],
            discount: $data['discount'] ?? 0,
            lateFee: $data['late_fee'] ?? 0,
            notes: $data['notes'] ?? null
        );
    }
}
