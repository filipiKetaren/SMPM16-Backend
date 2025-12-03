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

        // Pastikan start_month dan end_month ada nilainya
        $startMonth = $this->start_month ?? 1;
        $endMonth = $this->end_month ?? 12;

        // Jika start_month <= end_month (tidak melampaui tahun)
        if ($startMonth <= $endMonth) {
            for ($month = $startMonth; $month <= $endMonth; $month++) {
                $months[] = [
                    'month' => $month,
                    'year' => $this->start_date->year,
                    'month_name' => DateHelper::getMonthName($month) // Gunakan DateHelper
                ];
            }
        } else {
            // Jika start_month > end_month (melampaui tahun, contoh: Juli-Juni)
            // Bulan dari start_month sampai Desember
            for ($month = $startMonth; $month <= 12; $month++) {
                $months[] = [
                    'month' => $month,
                    'year' => $this->start_date->year,
                    'month_name' => DateHelper::getMonthName($month) // Gunakan DateHelper
                ];
            }

            // Bulan dari Januari sampai end_month
            for ($month = 1; $month <= $endMonth; $month++) {
                $months[] = [
                    'month' => $month,
                    'year' => $this->start_date->year + 1,
                    'month_name' => DateHelper::getMonthName($month) // Gunakan DateHelper
                ];
            }
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
