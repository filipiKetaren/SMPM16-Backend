<?php
// app/Repositories/Eloquent/FinanceReportRepository.php

namespace App\Repositories\Eloquent;

use App\Models\SppPayment;
use App\Models\SppPaymentDetail;
use App\Models\SavingsTransaction;
use App\Models\AcademicYear;
use App\Models\ReportLog;
use App\Models\ReportAccessLog;
use App\Repositories\Interfaces\FinanceReportRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinanceReportRepository implements FinanceReportRepositoryInterface
{
    public function getSppReportData(array $filters): array
    {
        $periodType = $filters['period_type'] ?? 'monthly';
        $year = $filters['year'] ?? date('Y');
        $month = $filters['month'] ?? null;
        $academicYearId = $filters['academic_year_id'] ?? null;

        $query = SppPayment::with([
            'student.class',
            'creator',
            'paymentDetails'
        ]);

        // Filter berdasarkan tahun akademik jika ada
        if ($academicYearId) {
            $academicYear = AcademicYear::find($academicYearId);
            if ($academicYear) {
                $query->whereBetween('payment_date', [
                    $academicYear->start_date,
                    $academicYear->end_date
                ]);
            }
        } else {
            // Filter berdasarkan tahun dan bulan
            if ($periodType === 'monthly' && $month) {
                $query->whereYear('payment_date', $year)
                      ->whereMonth('payment_date', $month);
            } else {
                $query->whereYear('payment_date', $year);
            }
        }

        $payments = $query->orderBy('payment_date', 'desc')->get();

        // Hitung statistik
        $totalIncome = $payments->sum('total_amount');
        $totalPayments = $payments->count();
        $totalStudents = $payments->unique('student_id')->count();

        // Group by payment method
        $paymentMethods = $payments->groupBy('payment_method')
            ->map(function ($items, $method) {
                return [
                    'method' => $method,
                    'count' => $items->count(),
                    'total' => $items->sum('total_amount')
                ];
            })->values();

        // Group by class
        $classSummary = $payments->groupBy('student.class.name')
            ->map(function ($items, $className) {
                return [
                    'class' => $className,
                    'total_payments' => $items->count(),
                    'total_amount' => $items->sum('total_amount'),
                    'total_students' => $items->unique('student_id')->count()
                ];
            })->values();

        return [
            'filters' => $filters,
            'summary' => [
                'total_income' => $totalIncome,
                'total_payments' => $totalPayments,
                'total_students' => $totalStudents,
                'average_payment' => $totalPayments > 0 ? $totalIncome / $totalPayments : 0,
            ],
            'payment_methods' => $paymentMethods,
            'class_summary' => $classSummary,
            'payments' => $payments->map(function ($payment) {
                return [
                    'receipt_number' => $payment->receipt_number,
                    'student_name' => $payment->student->full_name,
                    'student_nis' => $payment->student->nis,
                    'class' => $payment->student->class->name,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'total_amount' => $payment->total_amount,
                    'payment_method' => $payment->payment_method,
                    'created_by' => $payment->creator->full_name,
                    'months_paid' => $payment->paymentDetails->map(function ($detail) {
                        return [
                            'month' => $detail->month,
                            'year' => $detail->year,
                            'amount' => $detail->amount
                        ];
                    })
                ];
            }),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    public function getSavingsReportData(array $filters): array
    {
        $periodType = $filters['period_type'] ?? 'monthly';
        $year = $filters['year'] ?? date('Y');
        $month = $filters['month'] ?? null;
        $academicYearId = $filters['academic_year_id'] ?? null;

        $query = SavingsTransaction::with([
            'student.class',
            'creator'
        ]);

        // Filter berdasarkan tahun akademik jika ada
        if ($academicYearId) {
            $academicYear = AcademicYear::find($academicYearId);
            if ($academicYear) {
                $query->whereBetween('transaction_date', [
                    $academicYear->start_date,
                    $academicYear->end_date
                ]);
            }
        } else {
            // Filter berdasarkan tahun dan bulan
            if ($periodType === 'monthly' && $month) {
                $query->whereYear('transaction_date', $year)
                      ->whereMonth('transaction_date', $month);
            } else {
                $query->whereYear('transaction_date', $year);
            }
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->get();

        // Pisahkan deposit dan withdrawal
        $deposits = $transactions->where('transaction_type', 'deposit');
        $withdrawals = $transactions->where('transaction_type', 'withdrawal');

        // Hitung saldo akhir
        $currentBalance = $this->getCurrentSavingsBalance();

        // Group by student
        $studentSummary = $transactions->groupBy('student_id')
            ->map(function ($items, $studentId) {
                $student = $items->first()->student;
                $lastTransaction = $items->first();

                return [
                    'student_id' => $studentId,
                    'student_name' => $student->full_name,
                    'student_nis' => $student->nis,
                    'class' => $student->class->name,
                    'current_balance' => $lastTransaction->balance_after,
                    'total_deposits' => $items->where('transaction_type', 'deposit')->sum('amount'),
                    'total_withdrawals' => $items->where('transaction_type', 'withdrawal')->sum('amount'),
                    'last_transaction_date' => $lastTransaction->transaction_date->format('Y-m-d')
                ];
            })->values();

        return [
            'filters' => $filters,
            'summary' => [
                'total_deposits' => $deposits->sum('amount'),
                'total_withdrawals' => $withdrawals->sum('amount'),
                'total_transactions' => $transactions->count(),
                'current_balance' => $currentBalance,
                'net_flow' => $deposits->sum('amount') - $withdrawals->sum('amount'),
            ],
            'deposits_by_month' => $this->getMonthlyData($deposits, $year, $periodType, $month),
            'withdrawals_by_month' => $this->getMonthlyData($withdrawals, $year, $periodType, $month),
            'student_summary' => $studentSummary,
            'transactions' => $transactions->map(function ($transaction) {
                return [
                    'transaction_number' => $transaction->transaction_number,
                    'student_name' => $transaction->student->full_name,
                    'student_nis' => $transaction->student->nis,
                    'class' => $transaction->student->class->name,
                    'transaction_type' => $transaction->transaction_type,
                    'amount' => $transaction->amount,
                    'balance_before' => $transaction->balance_before,
                    'balance_after' => $transaction->balance_after,
                    'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                    'created_by' => $transaction->creator->full_name,
                    'notes' => $transaction->notes
                ];
            }),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get monthly data untuk chart
     */
    private function getMonthlyData($transactions, $year, $periodType, $month = null): array
    {
        $month = (int)$month; // Pastikan integer

        if ($periodType === 'monthly') {
            // Data per hari dalam bulan
            $daysInMonth = Carbon::create($year, $month)->daysInMonth;
            $data = [];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::create($year, $month, $day);
                $dailyTotal = $transactions->where('transaction_date', $date->format('Y-m-d'))->sum('amount');

                $data[] = [
                    'date' => $date->format('Y-m-d'),
                    'total' => $dailyTotal
                ];
            }
        } else {
            // Data per bulan dalam tahun
            $data = [];

            for ($currentMonth = 1; $currentMonth <= 12; $currentMonth++) {
                $monthlyTotal = $transactions->filter(function ($transaction) use ($year, $currentMonth) {
                    return $transaction->transaction_date->year == $year &&
                           $transaction->transaction_date->month == $currentMonth;
                })->sum('amount');

                $data[] = [
                    'month' => $currentMonth,
                    'month_name' => Carbon::create()->month($currentMonth)->locale('id')->monthName,
                    'total' => $monthlyTotal
                ];
            }
        }

        return $data;
    }

    /**
     * Get current total savings balance
     */
    private function getCurrentSavingsBalance(): float
    {
        $latestTransactions = SavingsTransaction::select('student_id', DB::raw('MAX(id) as latest_id'))
            ->groupBy('student_id')
            ->get()
            ->pluck('latest_id');

        return SavingsTransaction::whereIn('id', $latestTransactions)
            ->sum('balance_after');
    }

    public function createReportLog(array $data)
    {
        return ReportLog::create($data);
    }

    public function getReportHistory(array $filters)
    {
        $query = ReportLog::with('user')
            ->when(isset($filters['user_id']), function ($q) use ($filters) {
                $q->where('user_id', $filters['user_id']);
            })
            ->when(isset($filters['report_type']), function ($q) use ($filters) {
                $q->where('report_type', $filters['report_type']);
            })
            ->when(isset($filters['start_date']), function ($q) use ($filters) {
                $q->where('created_at', '>=', $filters['start_date']);
            })
            ->when(isset($filters['end_date']), function ($q) use ($filters) {
                $q->where('created_at', '<=', $filters['end_date']);
            });

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function cleanupOldReports(int $days = 7): int
    {
        $date = Carbon::now()->subDays($days);

        // Hanya hapus log lama, tidak ada file yang dihapus
        $logCount = ReportLog::where('created_at', '<', $date)->delete();
        $accessLogCount = ReportAccessLog::where('created_at', '<', $date)->delete();

        return $logCount + $accessLogCount;
    }
}
