<?php

namespace App\Repositories\Eloquent;

use App\Models\Student;
use App\Models\SppPayment;
use App\Models\SppPaymentDetail;
use App\Models\SavingsTransaction;
use App\Models\AcademicYear;
use App\Repositories\Interfaces\ParentFinanceRepositoryInterface;
use Carbon\Carbon;

class ParentFinanceRepository implements ParentFinanceRepositoryInterface
{
    public function getStudentIdsByParent(int $parentId): array
    {
        return Student::whereHas('parents', function($query) use ($parentId) {
            $query->where('parent_id', $parentId);
        })
        ->where('status', 'active')
        ->pluck('id')
        ->toArray();
    }

    public function getSppPaymentsByStudentIds(array $studentIds, ?string $year = null)
    {
        $query = SppPayment::with([
            'student.class.academicYear',
            'paymentDetails',
            'creator'
        ])
        ->whereIn('student_id', $studentIds)
        ->orderBy('payment_date', 'desc')
        ->orderBy('created_at', 'desc');

        if ($year) {
            $query->whereYear('payment_date', (int)$year);
        }

        return $query->get();
    }

    public function getSavingsTransactionsByStudentIds(array $studentIds, ?string $year = null)
    {
        $query = SavingsTransaction::with([
            'student.class',
            'creator'
        ])
        ->whereIn('student_id', $studentIds)
        ->orderBy('transaction_date', 'desc')
        ->orderBy('created_at', 'desc');

        if ($year) {
            $query->whereYear('transaction_date', (int)$year);
        }

        return $query->get();
    }

    public function getStudentCurrentSavingsBalance(int $studentId): float
    {
        $lastTransaction = SavingsTransaction::where('student_id', $studentId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastTransaction ? (float) $lastTransaction->balance_after : 0;
    }

    public function getStudentUnpaidMonths(int $studentId, ?string $year = null): array
    {
        $yearInt = $year ? (int)$year : null;
        $unpaidMonthsDetail = $this->getStudentUnpaidMonthsWithDetail($studentId, $yearInt);
        return array_column($unpaidMonthsDetail, 'month');
    }

    public function getStudentById(int $studentId)
    {
        return Student::with(['class.academicYear'])->find($studentId);
    }

    /**
     * Helper method: Dapatkan tahun akademik untuk siswa
     */
    private function getAcademicYearForStudent($student): ?AcademicYear
    {
        // Prioritaskan tahun akademik aktif
        $activeAcademicYear = AcademicYear::where('is_active', true)->first();
        if ($activeAcademicYear) {
            return $activeAcademicYear;
        }

        // Jika tidak ada tahun akademik aktif, gunakan dari kelas siswa
        if ($student->class && $student->class->academicYear) {
            return $student->class->academicYear;
        }

        return null;
    }

    public function getStudentTotalSppPaid(int $studentId, int $year): float
    {
        // Hitung berdasarkan tahun akademik
        $student = $this->getStudentById($studentId);
        if (!$student || !$student->class || !$student->class->academicYear) {
            return 0;
        }

        $academicYear = $student->class->academicYear;

        return (float) SppPayment::where('student_id', $studentId)
            ->whereBetween('payment_date', [
                $academicYear->start_date,
                $academicYear->end_date
            ])
            ->sum('total_amount');
    }

    /**
     * Dapatkan info tahun akademik untuk siswa
     */
    public function getStudentAcademicYearInfo(int $studentId): ?array
    {
        $student = $this->getStudentById($studentId);
        if (!$student) {
            return null;
        }

        $academicYear = $this->getAcademicYearForStudent($student);
        if (!$academicYear) {
            return null;
        }

        return [
            'id' => $academicYear->id,
            'name' => $academicYear->name,
            'start_date' => $academicYear->start_date->format('Y-m-d'),
            'end_date' => $academicYear->end_date->format('Y-m-d'),
            'start_month' => $academicYear->start_month,
            'end_month' => $academicYear->end_month,
            'is_active' => $academicYear->is_active
        ];
    }

    /**
     * Dapatkan detail unpaid months untuk tahun akademik (dengan opsi filter tahun)
     */
    public function getStudentUnpaidMonthsWithDetail(int $studentId, ?int $year = null): array
    {
        $student = $this->getStudentById($studentId);
        if (!$student) {
            return [];
        }

        // Dapatkan tahun akademik
        $academicYear = $this->getAcademicYearForStudent($student);
        if (!$academicYear) {
            return [];
        }

        // Dapatkan SEMUA bulan akademik
        $allAcademicMonths = $academicYear->getAcademicMonths();

        // Dapatkan SEMUA bulan yang sudah dibayar dalam rentang tahun akademik
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

        // Konversi paid months ke format yang mudah dicek
        $paidMonthsMap = [];
        foreach ($paidMonthsDetails as $paid) {
            $key = $paid['month'] . '-' . $paid['year'];
            $paidMonthsMap[$key] = $paid['amount'];
        }

        // Hitung bulan yang belum dibayar
        $unpaidMonths = [];
        foreach ($allAcademicMonths as $month) {
            // Jika ada filter tahun, hanya tampilkan bulan di tahun tersebut
            if ($year && $month['year'] != $year) {
                continue;
            }

            $key = $month['month'] . '-' . $month['year'];
            if (!isset($paidMonthsMap[$key])) {
                $unpaidMonths[] = [
                    'month' => $month['month'],
                    'year' => $month['year'],
                    'month_name' => $month['month_name']
                ];
            }
        }

        // Urutkan berdasarkan tahun lalu bulan
        usort($unpaidMonths, function($a, $b) {
            if ($a['year'] == $b['year']) {
                return $a['month'] - $b['month'];
            }
            return $a['year'] - $b['year'];
        });

        return $unpaidMonths;
    }

    public function getStudentLastSavingsTransaction(int $studentId)
    {
        return SavingsTransaction::where('student_id', $studentId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    public function getStudentTotalSavingsDeposits(int $studentId): float
    {
        return (float) SavingsTransaction::where('student_id', $studentId)
            ->where('transaction_type', 'deposit')
            ->sum('amount');
    }

    public function getStudentTotalSavingsWithdrawals(int $studentId): float
    {
        return (float) SavingsTransaction::where('student_id', $studentId)
            ->where('transaction_type', 'withdrawal')
            ->sum('amount');
    }

    public function getStudentSavingsTransactionsByMonth(int $studentId, int $year, int $month)
    {
        return SavingsTransaction::where('student_id', $studentId)
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->orderBy('transaction_date', 'desc')
            ->get();
    }

    /**
     * Get SPP payments with advanced filters
     */
    public function getSppPaymentsByStudentIdsWithFilters(
        array $studentIds,
        ?string $year = null,
        ?string $month = null,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        $query = SppPayment::with([
            'student.class.academicYear',
            'paymentDetails',
            'creator'
        ])
        ->whereIn('student_id', $studentIds)
        ->orderBy('payment_date', 'desc')
        ->orderBy('created_at', 'desc');

        // Gunakan whereDate untuk filter rentang tanggal
        if ($startDate && $endDate) {
            $query->whereDate('payment_date', '>=', $startDate)
                ->whereDate('payment_date', '<=', $endDate);
        } else {
            // Apply year filter
            if ($year) {
                $query->whereYear('payment_date', (int)$year);
            }

            // Apply month filter
            if ($month) {
                $query->whereMonth('payment_date', (int)$month);
            }
        }

        return $query->get();
    }

    /**
     * Get savings transactions with advanced filters
     */
    public function getSavingsTransactionsByStudentIdsWithFilters(
        array $studentIds,
        ?string $year = null,
        ?string $month = null,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        $query = SavingsTransaction::with([
            'student.class',
            'creator'
        ])
        ->whereIn('student_id', $studentIds)
        ->orderBy('transaction_date', 'desc')
        ->orderBy('created_at', 'desc');

        // Gunakan whereDate untuk filter rentang tanggal
        if ($startDate && $endDate) {
            $query->whereDate('transaction_date', '>=', $startDate)
                ->whereDate('transaction_date', '<=', $endDate);
        } else {
            // Apply year filter
            if ($year) {
                $query->whereYear('transaction_date', (int)$year);
            }

            // Apply month filter
            if ($month) {
                $query->whereMonth('transaction_date', (int)$month);
            }
        }

        return $query->get();
    }

    /**
     * Get total SPP paid with filters
     */
    public function getStudentTotalSppPaidWithFilters(
        int $studentId,
        ?string $year = null,
        ?string $month = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): float {
        $query = SppPayment::where('student_id', $studentId);

        // Gunakan whereDate untuk filter rentang tanggal
        if ($startDate && $endDate) {
            $query->whereDate('payment_date', '>=', $startDate)
                ->whereDate('payment_date', '<=', $endDate);
        } else {
            // Apply year filter
            if ($year) {
                $query->whereYear('payment_date', (int)$year);
            }

            // Apply month filter
            if ($month) {
                $query->whereMonth('payment_date', (int)$month);
            }
        }

        return (float) $query->sum('total_amount');
    }
}
