<?php

namespace App\DTOs;

class UpdatePaymentData
{
    public function __construct(
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

         private static function getSafeValue(array $data, string $key, $default = null)
    {
        $value = $data[$key] ?? $default;

        // Handle empty strings untuk numerik fields
        if (($key === 'subtotal' || $key === 'total_amount' || $key === 'discount' || $key === 'late_fee') && $value === '') {
            return 0;
        }

        return $value;
    }
}
