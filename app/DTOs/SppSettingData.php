<?php

namespace App\DTOs;

use Carbon\Carbon;

class SppSettingData
{
    public function __construct(
        public int $academicYearId,
        public int $gradeLevel,
        public float $monthlyAmount,
        public int $dueDate,
        public bool $lateFeeEnabled,
        public ?string $lateFeeType = null,
        public ?float $lateFeeAmount = null,
        public ?int $lateFeeStartDay = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            academicYearId: self::getIntegerValue($data, 'academic_year_id'),
            gradeLevel: self::getIntegerValue($data, 'grade_level'),
            monthlyAmount: self::getFloatValue($data, 'monthly_amount'),
            dueDate: self::getIntegerValue($data, 'due_date'),
            lateFeeEnabled: self::getBooleanValue($data, 'late_fee_enabled'),
            lateFeeType: self::getNullableStringValue($data, 'late_fee_type'),
            lateFeeAmount: self::getNullableFloatValue($data, 'late_fee_amount'),
            lateFeeStartDay: self::getNullableIntegerValue($data, 'late_fee_start_day')
        );
    }

    /**
     * Validasi dan konversi untuk integer
     */
    private static function getIntegerValue(array $data, string $key): int
    {
        if (!isset($data[$key])) {
            throw new \InvalidArgumentException("Field {$key} harus diisi");
        }

        $value = $data[$key];
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Field {$key} harus berupa angka");
        }

        return (int) $value;
    }

    /**
     * Validasi dan konversi untuk float
     */
    private static function getFloatValue(array $data, string $key): float
    {
        if (!isset($data[$key])) {
            throw new \InvalidArgumentException("Field {$key} harus diisi");
        }

        $value = $data[$key];
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Field {$key} harus berupa angka");
        }

        return (float) $value;
    }

    /**
     * Validasi dan konversi untuk boolean dengan ketat
     */
    private static function getBooleanValue(array $data, string $key): bool
    {
        if (!isset($data[$key])) {
            return false;
        }

        $value = $data[$key];

        // Accept berbagai format boolean
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $lowerValue = strtolower($value);
            $trueValues = ['true', '1', 'yes', 'on', 'enable'];
            $falseValues = ['false', '0', 'no', 'off', 'disable'];

            if (in_array($lowerValue, $trueValues)) {
                return true;
            }

            if (in_array($lowerValue, $falseValues)) {
                return false;
            }

            // Jika bukan nilai boolean yang valid, throw exception
            throw new \InvalidArgumentException("Field {$key} harus berupa boolean (true/false, 1/0)");
        }

        throw new \InvalidArgumentException("Field {$key} harus berupa boolean");
    }

    /**
     * Method untuk nullable values
     */
    private static function getNullableStringValue(array $data, string $key): ?string
    {
        if (!isset($data[$key]) || $data[$key] === '') {
            return null;
        }

        return (string) $data[$key];
    }

    private static function getNullableIntegerValue(array $data, string $key): ?int
    {
        if (!isset($data[$key]) || $data[$key] === '') {
            return null;
        }

        if (!is_numeric($data[$key])) {
            throw new \InvalidArgumentException("Field {$key} harus berupa angka");
        }

        return (int) $data[$key];
    }

    private static function getNullableFloatValue(array $data, string $key): ?float
    {
        if (!isset($data[$key]) || $data[$key] === '') {
            return null;
        }

        if (!is_numeric($data[$key])) {
            throw new \InvalidArgumentException("Field {$key} harus berupa angka");
        }

        return (float) $data[$key];
    }

    public function toArray(): array
    {
        return [
            'academic_year_id' => $this->academicYearId,
            'grade_level' => $this->gradeLevel,
            'monthly_amount' => $this->monthlyAmount,
            'due_date' => $this->dueDate,
            'late_fee_enabled' => $this->lateFeeEnabled,
            'late_fee_type' => $this->lateFeeType,
            'late_fee_amount' => $this->lateFeeAmount,
            'late_fee_start_day' => $this->lateFeeStartDay,
        ];
    }
}
