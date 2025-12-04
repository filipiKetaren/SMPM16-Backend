<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Scholarship extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'scholarship_name',
        'type',
        'discount_percentage',
        'discount_amount',
        'start_date',
        'end_date',
        'academic_year_id',
        'status',
        'description',
        'sponsor',
        'requirements'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class)->nullable();
    }

    /**
     * Check if scholarship is currently active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
               Carbon::now()->between($this->start_date, $this->end_date);
    }

    /**
     * Calculate discount for a given amount
     */
    public function calculateDiscount(float $amount): float
    {
        if ($this->type === 'full') {
            return $amount; // 100% discount
        }

        if ($this->discount_amount) {
            return min($this->discount_amount, $amount);
        }

        return $amount * ($this->discount_percentage / 100);
    }

    /**
     * Scope for active scholarships
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('start_date', '<=', Carbon::now())
                     ->where('end_date', '>=', Carbon::now());
    }

    /**
     * Scope for scholarships that are active for a specific month
     */
    public function scopeActiveForMonth($query, $year, $month)
    {
        $date = Carbon::create($year, $month, 1);
        return $query->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date);
    }

    /**
     * Automatically update status based on dates
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($scholarship) {
            $now = Carbon::now();

            if ($scholarship->end_date < $now) {
                $scholarship->status = 'expired';
            } elseif ($scholarship->start_date <= $now && $scholarship->end_date >= $now) {
                $scholarship->status = 'active';
            } else {
                $scholarship->status = 'inactive';
            }
        });
    }

    /**
     * Get discount description for display
     */
    public function getDiscountDescriptionAttribute(): string
    {
        if ($this->type === 'full') {
            return '100% Gratis SPP';
        }

        if ($this->discount_amount) {
            return 'Potongan Rp ' . number_format($this->discount_amount, 0, ',', '.') . ' per bulan';
        }

        return 'Potongan ' . $this->discount_percentage . '% per bulan';
    }
}
