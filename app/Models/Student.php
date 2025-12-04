<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'nis', 'full_name', 'class_id', 'photo', 'birth_date',
        'gender', 'address', 'parent_phone', 'parent_email',
        'status', 'admission_date'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'admission_date' => 'date',
    ];

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function sppPayments()
    {
        return $this->hasMany(SppPayment::class);
    }

    public function savingsTransactions()
    {
        return $this->hasMany(SavingsTransaction::class);
    }

    public function parents()
    {
        return $this->belongsToMany(ParentModel::class, 'parent_student', 'student_id', 'parent_id')
                    ->withPivot('relationship')
                    ->withTimestamps();
    }

    // Relationship dengan scholarships
    public function scholarships()
    {
        return $this->hasMany(Scholarship::class);
    }

    /**
     * Get active scholarship for student
     */
    public function getActiveScholarship()
    {
        return $this->scholarships()
            ->active()
            ->first();
    }

    /**
     * Get scholarship for specific month
     */
    public function getScholarshipForMonth(int $year, int $month)
    {
        return $this->scholarships()
            ->activeForMonth($year, $month)
            ->first();
    }

    /**
     * Check if student has active scholarship
     */
    public function hasActiveScholarship(): bool
    {
        return $this->getActiveScholarship() !== null;
    }

    /**
     * Check if student has active scholarship for specific month
     */
    public function hasActiveScholarshipForMonth(int $year, int $month): bool
    {
        return $this->getScholarshipForMonth($year, $month) !== null;
    }

    /**
     * Calculate SPP amount after scholarship discount for specific month
     */
    public function calculateScholarshipDiscountForMonth(float $originalAmount, int $year, int $month): array
    {
        $scholarship = $this->getScholarshipForMonth($year, $month);

        if (!$scholarship) {
            return [
                'has_scholarship' => false,
                'original_amount' => $originalAmount,
                'discount_amount' => 0,
                'final_amount' => $originalAmount,
                'scholarship' => null
            ];
        }

        $discountAmount = $scholarship->calculateDiscount($originalAmount);
        $finalAmount = max(0, $originalAmount - $discountAmount);

        return [
            'has_scholarship' => true,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'scholarship' => [
                'id' => $scholarship->id,
                'name' => $scholarship->scholarship_name,
                'type' => $scholarship->type,
                'discount_percentage' => (float) $scholarship->discount_percentage,
                'discount_amount' => (float) $scholarship->discount_amount,
                'discount_description' => $scholarship->discount_description,
                'sponsor' => $scholarship->sponsor,
                'start_date' => $scholarship->start_date->format('Y-m-d'),
                'end_date' => $scholarship->end_date->format('Y-m-d'),
                'status' => $scholarship->status,
                'is_active' => $scholarship->isActive()
            ]
        ];
    }

    /**
     * Calculate scholarship discount for current month
     */
    public function calculateScholarshipDiscount(float $originalAmount): array
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        return $this->calculateScholarshipDiscountForMonth($originalAmount, $currentYear, $currentMonth);
    }

    /**
     * Get detailed scholarship information for display
     */
    public function getScholarshipDetails(): ?array
    {
        $scholarship = $this->getActiveScholarship();

        if (!$scholarship) {
            return null;
        }

        return [
            'id' => $scholarship->id,
            'name' => $scholarship->scholarship_name,
            'type' => $scholarship->type,
            'type_label' => $scholarship->type === 'full' ? 'Beasiswa Penuh' : 'Beasiswa Parsial',
            'discount_percentage' => $scholarship->discount_percentage ? (float) $scholarship->discount_percentage : null,
            'discount_amount' => $scholarship->discount_amount ? (float) $scholarship->discount_amount : null,
            'discount_description' => $scholarship->discount_description,
            'start_date' => $scholarship->start_date->format('Y-m-d'),
            'end_date' => $scholarship->end_date->format('Y-m-d'),
            'sponsor' => $scholarship->sponsor,
            'description' => $scholarship->description,
            'status' => $scholarship->status,
            'is_active' => $scholarship->isActive()
        ];
    }

    /**
     * Method untuk menghitung saldo tabungan saat ini
     */
    public function getCurrentSavingsBalance()
    {
        $lastTransaction = $this->savingsTransactions()
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastTransaction ? $lastTransaction->balance_after : 0;
    }

    /**
     * Get scholarship for specific month without checking expired status
     * Ini untuk keperluan pembayaran historis
     */
    public function getScholarshipForMonthIgnoreStatus(int $year, int $month)
    {
        $date = Carbon::create($year, $month, 1);
        return $this->scholarships()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    /**
     * Calculate SPP amount after scholarship discount for specific month (ignore expired status)
     */
    public function calculateScholarshipDiscountForMonthIgnoreStatus(float $originalAmount, int $year, int $month): array
    {
        $scholarship = $this->getScholarshipForMonthIgnoreStatus($year, $month);

        if (!$scholarship) {
            return [
                'has_scholarship' => false,
                'original_amount' => $originalAmount,
                'discount_amount' => 0,
                'final_amount' => $originalAmount,
                'scholarship' => null
            ];
        }

        $discountAmount = $scholarship->calculateDiscount($originalAmount);
        $finalAmount = max(0, $originalAmount - $discountAmount);

        return [
            'has_scholarship' => true,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'scholarship' => [
                'id' => $scholarship->id,
                'name' => $scholarship->scholarship_name,
                'type' => $scholarship->type,
                'discount_percentage' => (float) $scholarship->discount_percentage,
                'discount_amount' => (float) $scholarship->discount_amount,
                'discount_description' => $scholarship->discount_description,
                'sponsor' => $scholarship->sponsor,
                'start_date' => $scholarship->start_date->format('Y-m-d'),
                'end_date' => $scholarship->end_date->format('Y-m-d'),
                'status' => $scholarship->status
            ]
        ];
    }
}
