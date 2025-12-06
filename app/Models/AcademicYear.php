<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Helpers\DateHelper; // Import DateHelper

class AcademicYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'start_month',
        'end_month',
        'allow_partial_payment',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'allow_partial_payment' => 'boolean',
        'start_month' => 'integer',
        'end_month' => 'integer',
    ];

    public function classes()
    {
        return $this->hasMany(ClassModel::class);
    }

    public function sppSettings()
    {
        return $this->hasMany(SppSetting::class);
    }

    /**
     * Get academic months for this academic year
     */
    public function getAcademicMonths(): array
    {
        $months = [];

        $start = \Carbon\Carbon::parse($this->start_date);
        $end = \Carbon\Carbon::parse($this->end_date);

        $current = $start->copy();

        while ($current <= $end) {
            $months[] = [
                'month' => $current->month,
                'year' => $current->year,
                'month_name' => \App\Helpers\DateHelper::getMonthName($current->month)
            ];
            $current->addMonth();
        }

        return $months;
    }

    /**
     * Check if a specific month-year is within academic year
     */
    public function isWithinAcademicYear(int $month, int $year): bool
    {
        foreach ($this->getAcademicMonths() as $academicMonth) {
            if ($academicMonth['month'] == $month && $academicMonth['year'] == $year) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get current academic month based on today's date
     */
    public function getCurrentAcademicMonth(): ?array
    {
        $today = Carbon::now();

        foreach ($this->getAcademicMonths() as $academicMonth) {
            // Cek apakah bulan dan tahun ini ada dalam bulan akademik
            if ($academicMonth['month'] == $today->month && $academicMonth['year'] == $today->year) {
                return $academicMonth;
            }
        }

        return null;
    }
}
