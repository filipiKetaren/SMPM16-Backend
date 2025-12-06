<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Models\AcademicYear;
use App\Models\SppPaymentDetail;
use Illuminate\Support\Facades\Log;

class FinanceHelper
{
    /**
     * Validasi parameter filter
     */
    public static function validateFilterParams(
        ?string $year,
        ?string $month,
        ?string $startDate,
        ?string $endDate
    ): ?array {
        // Validasi tahun
        if ($year && !self::isValidYear($year)) {
            return [
                'status' => 'error',
                'message' => 'Validasi tahun gagal',
                'errors' => ['year' => ['Format tahun tidak valid. Gunakan format YYYY.']],
                'code' => 422
            ];
        }

        // Validasi bulan
        if ($month && !self::isValidMonth($month)) {
            return [
                'status' => 'error',
                'message' => 'Validasi bulan gagal',
                'errors' => ['month' => ['Format bulan tidak valid. Gunakan angka 1-12.']],
                'code' => 422
            ];
        }

        // Validasi tanggal
        if ($startDate && !self::isValidDate($startDate)) {
            return [
                'status' => 'error',
                'message' => 'Validasi tanggal mulai gagal',
                'errors' => ['start_date' => ['Format tanggal mulai tidak valid.']],
                'code' => 422
            ];
        }

        if ($endDate && !self::isValidDate($endDate)) {
            return [
                'status' => 'error',
                'message' => 'Validasi tanggal akhir gagal',
                'errors' => ['end_date' => ['Format tanggal akhir tidak valid.']],
                'code' => 422
            ];
        }

        // Validasi rentang tanggal
        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            if ($start->gt($end)) {
                return [
                    'status' => 'error',
                    'message' => 'Validasi rentang tanggal gagal',
                    'errors' => ['date_range' => ['Tanggal mulai tidak boleh lebih besar dari tanggal akhir.']],
                    'code' => 422
                ];
            }
        }

        return null;
    }

    /**
     * Dapatkan informasi periode berdasarkan filter
     */
    public static function getPeriodInfo(
        ?string $year,
        ?string $month,
        ?string $startDate,
        ?string $endDate
    ): array {
        if ($startDate && $endDate) {
            try {
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);

                return [
                    'type' => 'date_range',
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'display' => "Periode {$start->translatedFormat('d F Y')} - {$end->translatedFormat('d F Y')}"
                ];
            } catch (\Exception $e) {
                return [
                    'type' => 'date_range',
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'display' => "Periode {$startDate} - {$endDate}"
                ];
            }
        } elseif ($year && $month) {
            $monthName = DateHelper::getMonthName((int)$month);
            return [
                'type' => 'month',
                'year' => (int)$year,
                'month' => (int)$month,
                'month_name' => $monthName,
                'display' => "{$monthName} {$year}"
            ];
        } elseif ($year) {
            return [
                'type' => 'year',
                'year' => (int)$year,
                'display' => "Tahun {$year}"
            ];
        } else {
            $currentYear = Carbon::now()->year;
            return [
                'type' => 'current_year',
                'year' => $currentYear,
                'display' => "Tahun {$currentYear}"
            ];
        }
    }

    /**
     * Dapatkan daftar filter yang diterapkan
     */
    public static function getAppliedFilters(
        ?string $year,
        ?string $month,
        ?string $startDate,
        ?string $endDate
    ): array {
        $filters = [];

        if ($year) $filters['year'] = $year;
        if ($month) $filters['month'] = $month;
        if ($startDate) $filters['start_date'] = $startDate;
        if ($endDate) $filters['end_date'] = $endDate;

        return $filters;
    }

    /**
     * Hitung unpaid months dengan filter
     */
    public static function calculateUnpaidMonthsWithFilters(
        int $studentId,
        AcademicYear $academicYear,
        ?string $filterYear = null,
        ?string $filterMonth = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $allAcademicMonths = $academicYear->getAcademicMonths();

        // Dapatkan bulan yang sudah dibayar
        $paidMonthsDetails = SppPaymentDetail::whereHas('payment', function($query) use ($studentId, $academicYear) {
            $query->where('student_id', $studentId)
                ->whereBetween('payment_date', [
                    $academicYear->start_date,
                    $academicYear->end_date
                ]);
        })
        ->select('month', 'year', 'amount')
        ->get()
        ->toArray();

        // Konversi ke map
        $paidMonthsMap = [];
        foreach ($paidMonthsDetails as $paid) {
            $key = $paid['month'] . '-' . $paid['year'];
            $paidMonthsMap[$key] = $paid['amount'];
        }

        // Filter bulan akademik
        $filteredMonths = [];
        foreach ($allAcademicMonths as $academicMonth) {
            $key = $academicMonth['month'] . '-' . $academicMonth['year'];

            // Skip jika sudah dibayar
            if (isset($paidMonthsMap[$key])) {
                continue;
            }

            // Filter berdasarkan tahun
            if ($filterYear && $academicMonth['year'] != (int)$filterYear) {
                continue;
            }

            // Filter berdasarkan bulan
            if ($filterMonth && $academicMonth['month'] != (int)$filterMonth) {
                continue;
            }

            // Filter berdasarkan rentang tanggal
            if ($startDate && $endDate) {
                $monthDate = Carbon::create($academicMonth['year'], $academicMonth['month'], 1);
                $filterStart = Carbon::parse($startDate);
                $filterEnd = Carbon::parse($endDate);

                if ($monthDate->lt($filterStart) || $monthDate->gt($filterEnd)) {
                    continue;
                }
            }

            $filteredMonths[] = [
                'month' => $academicMonth['month'],
                'year' => $academicMonth['year'],
                'month_name' => $academicMonth['month_name']
            ];
        }

        // Urutkan
        usort($filteredMonths, function($a, $b) {
            if ($a['year'] == $b['year']) {
                return $a['month'] - $b['month'];
            }
            return $a['year'] - $b['year'];
        });

        return $filteredMonths;
    }

    /**
     * Normalisasi rentang tanggal
     */
    public static function normalizeDateRange(?string $startDate, ?string $endDate): array
    {
        if (!$startDate || !$endDate) {
            return [$startDate, $endDate];
        }

        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            // Jika tanggal akhir tidak valid, perbaiki
            if (!checkdate($end->month, $end->day, $end->year)) {
                $lastDay = Carbon::create($end->year, $end->month, 1)->endOfMonth()->day;
                $end = Carbon::create($end->year, $end->month, $lastDay);
                $endDate = $end->format('Y-m-d');
            }

            return [$startDate, $endDate];
        } catch (\Exception $e) {
            return [$startDate, $endDate];
        }
    }

    /**
     * Helper validation methods
     */
    public static function isValidYear(string $year): bool
    {
        return preg_match('/^\d{4}$/', $year) && $year >= 2020 && $year <= 2030;
    }

    public static function isValidMonth(string $month): bool
    {
        return preg_match('/^(0?[1-9]|1[0-2])$/', $month) && $month >= 1 && $month <= 12;
    }

    public static function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = explode('-', $date);
        return checkdate((int)$month, (int)$day, (int)$year);
    }
}
