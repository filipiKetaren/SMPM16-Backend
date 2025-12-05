<?php

namespace App\Repositories\Eloquent;

use App\Models\Student;
use App\Models\SppPayment;
use App\Models\SppPaymentDetail;
use App\Models\SavingsTransaction;
use App\Models\AcademicYear;
use App\Repositories\Interfaces\ParentFinanceRepositoryInterface;
use Illuminate\Support\Facades\DB;
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
            $query->whereYear('payment_date', $year);
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
            $query->whereYear('transaction_date', $year);
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

    /**
     * PERBAIKAN: Hitung unpaid months berdasarkan tahun akademik
     */
    public function getStudentUnpaidMonths(int $studentId, int $year): array
    {
        // Dapatkan data siswa dengan kelas dan tahun akademik
        $student = Student::with(['class.academicYear'])->find($studentId);

        if (!$student || !$student->class || !$student->class->academicYear) {
            return [];
        }

        $academicYear = $student->class->academicYear;

        // Cek apakah tahun yang diminta sesuai dengan tahun akademik
        $startYear = (int) Carbon::parse($academicYear->start_date)->year;
        $endYear = (int) Carbon::parse($academicYear->end_date)->year;

        // Jika tahun yang diminta tidak termasuk dalam tahun akademik
        if ($year < $startYear || $year > $endYear) {
            return [];
        }

        // Dapatkan daftar bulan akademik untuk tahun yang diminta
        $academicMonths = $this->getAcademicMonthsForYear($academicYear, $year);

        if (empty($academicMonths)) {
            return [];
        }

        // Dapatkan bulan yang sudah dibayar
        $paidMonths = SppPaymentDetail::whereHas('payment', function($query) use ($studentId, $academicYear) {
            $query->where('student_id', $studentId)
                  ->whereBetween('payment_date', [
                      $academicYear->start_date,
                      $academicYear->end_date
                  ]);
        })
        ->whereIn('year', array_unique(array_column($academicMonths, 'year')))
        ->pluck('month')
        ->toArray();

        // Hitung bulan yang belum dibayar
        $allAcademicMonths = array_column($academicMonths, 'month');
        $unpaidMonths = array_diff($allAcademicMonths, $paidMonths);

        return array_values($unpaidMonths);
    }

    /**
     * Helper: Dapatkan bulan akademik untuk tahun tertentu
     */
    private function getAcademicMonthsForYear($academicYear, int $year): array
    {
        $academicMonths = [];
        $current = Carbon::parse($academicYear->start_date);
        $end = Carbon::parse($academicYear->end_date);

        while ($current <= $end) {
            if ($current->year == $year) {
                $academicMonths[] = [
                    'month' => $current->month,
                    'year' => $current->year,
                    'month_name' => $current->translatedFormat('F')
                ];
            }
            $current->addMonth();
        }

        return $academicMonths;
    }

    public function getStudentById(int $studentId)
    {
        return Student::with(['class.academicYear'])->find($studentId);
    }

    // ================== METHOD BARU ==================

    public function getStudentTotalSppPaid(int $studentId, int $year): float
    {
        // PERBAIKAN: Hitung berdasarkan tahun akademik
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
     * PERBAIKAN BARU: Dapatkan detail unpaid months dengan tahun akademik
     */
    public function getStudentUnpaidMonthsWithDetail(int $studentId, int $year): array
    {
        $student = $this->getStudentById($studentId);

        if (!$student || !$student->class || !$student->class->academicYear) {
            return [];
        }

        $academicYear = $student->class->academicYear;

        // Cek apakah tahun yang diminta sesuai dengan tahun akademik
        $startYear = (int) Carbon::parse($academicYear->start_date)->year;
        $endYear = (int) Carbon::parse($academicYear->end_date)->year;

        if ($year < $startYear || $year > $endYear) {
            return [];
        }

        // Dapatkan daftar bulan akademik untuk tahun yang diminta
        $academicMonths = $this->getAcademicMonthsForYear($academicYear, $year);

        if (empty($academicMonths)) {
            return [];
        }

        // Dapatkan bulan yang sudah dibayar dengan detail
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
        foreach ($academicMonths as $month) {
            $key = $month['month'] . '-' . $month['year'];
            if (!isset($paidMonthsMap[$key])) {
                $unpaidMonths[] = [
                    'month' => $month['month'],
                    'year' => $month['year'],
                    'month_name' => $month['month_name']
                ];
            }
        }

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
        return (float) SavingsTransaction::where('studentId', $studentId)
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
     * Dapatkan info tahun akademik untuk siswa
     */
    public function getStudentAcademicYearInfo(int $studentId): ?array
    {
        $student = $this->getStudentById($studentId);

        if (!$student || !$student->class || !$student->class->academicYear) {
            return null;
        }

        $academicYear = $student->class->academicYear;

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
}
