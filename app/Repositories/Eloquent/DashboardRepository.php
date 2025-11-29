<?php

namespace App\Repositories\Eloquent;

use App\Models\Student;
use App\Models\SppPayment;
use App\Models\SppPaymentDetail;
use App\Models\SavingsTransaction;
use App\Repositories\Interfaces\DashboardRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function getTotalSppThisMonth(int $year, int $month): float
    {
        return SppPayment::whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->sum('total_amount');
    }

    public function getTotalSppThisYear(int $year): float
    {
        return SppPayment::whereYear('payment_date', $year)
            ->sum('total_amount');
    }

    public function getUnpaidStudentsCount(int $year, int $month): int
    {
        // Dapatkan semua siswa aktif
        $activeStudents = Student::where('status', 'active')->count();

        // Dapatkan siswa yang sudah bayar bulan ini
        $paidStudents = SppPaymentDetail::where('year', $year)
            ->where('month', $month)
            ->join('spp_payments', 'spp_payment_details.payment_id', '=', 'spp_payments.id')
            ->distinct('spp_payments.student_id')
            ->count('spp_payments.student_id');

        return $activeStudents - $paidStudents;
    }

    public function getTotalActiveStudents(): int
    {
        return Student::where('status', 'active')->count();
    }

    public function getTotalAlumniStudents(): int
    {
        return Student::where('status', 'alumni')->count();
    }

    public function getSppMonthlyData(int $year): array
    {
        // Gunakan data dari spp_payments (berdasarkan payment_date)
        $monthlyData = SppPayment::whereYear('payment_date', $year)
            ->select(
                DB::raw('MONTH(payment_date) as month'),
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy(DB::raw('MONTH(payment_date)'))
            ->orderBy('month')
            ->get()
            ->keyBy('month')
            ->toArray();

        // Format data untuk semua bulan
        $formattedData = [];
        for ($month = 1; $month <= 12; $month++) {
            $formattedData[] = [
                'month' => $month,
                'month_name' => \App\Helpers\DateHelper::getMonthName($month),
                'total' => isset($monthlyData[$month]) ? (float) $monthlyData[$month]['total'] : 0
            ];
        }

        return $formattedData;
    }

    public function getRecentPayments(int $limit = 5): array
    {
        return SppPayment::with(['student.class', 'creator'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'student_name' => $payment->student->full_name,
                    'class' => $payment->student->class->name,
                    'amount' => (float) $payment->total_amount,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'payment_method' => $payment->payment_method,
                    'created_by' => $payment->creator->full_name
                ];
            })
            ->toArray();
    }

    public function getStudentCountByClass(): array
    {
        return Student::where('status', 'active')
            ->with('class')
            ->get()
            ->groupBy('class.name')
            ->map(function ($students, $className) {
                return [
                    'class_name' => $className,
                    'student_count' => $students->count()
                ];
            })
            ->values()
            ->toArray();
    }

    // ✅ METHOD BARU UNTUK TABUNGAN
    public function getTotalSavingsDepositsThisMonth(int $year, int $month): float
    {
        return SavingsTransaction::whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->where('transaction_type', 'deposit')
            ->sum('amount');
    }

    public function getTotalSavingsWithdrawalsThisMonth(int $year, int $month): float
    {
        return SavingsTransaction::whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->where('transaction_type', 'withdrawal')
            ->sum('amount');
    }

    public function getTotalSavingsBalance(): float
    {
        // Ambil saldo terakhir dari setiap siswa
        $latestTransactions = SavingsTransaction::select('student_id', DB::raw('MAX(id) as latest_id'))
            ->groupBy('student_id')
            ->get()
            ->pluck('latest_id');

        return SavingsTransaction::whereIn('id', $latestTransactions)
            ->sum('balance_after');
    }

    public function getSavingsMonthlyData(int $year): array
    {
        // Data deposit per bulan
        $monthlyDeposits = SavingsTransaction::whereYear('transaction_date', $year)
            ->where('transaction_type', 'deposit')
            ->select(
                DB::raw('MONTH(transaction_date) as month'),
                DB::raw('SUM(amount) as total_deposits')
            )
            ->groupBy(DB::raw('MONTH(transaction_date)'))
            ->orderBy('month')
            ->get()
            ->keyBy('month')
            ->toArray();

        // Data withdrawal per bulan
        $monthlyWithdrawals = SavingsTransaction::whereYear('transaction_date', $year)
            ->where('transaction_type', 'withdrawal')
            ->select(
                DB::raw('MONTH(transaction_date) as month'),
                DB::raw('SUM(amount) as total_withdrawals')
            )
            ->groupBy(DB::raw('MONTH(transaction_date)'))
            ->orderBy('month')
            ->get()
            ->keyBy('month')
            ->toArray();

        // Format data untuk semua bulan
        $formattedData = [];
        for ($month = 1; $month <= 12; $month++) {
            $formattedData[] = [
                'month' => $month,
                'month_name' => \App\Helpers\DateHelper::getMonthName($month),
                'deposits' => isset($monthlyDeposits[$month]) ? (float) $monthlyDeposits[$month]['total_deposits'] : 0,
                'withdrawals' => isset($monthlyWithdrawals[$month]) ? (float) $monthlyWithdrawals[$month]['total_withdrawals'] : 0
            ];
        }

        return $formattedData;
    }

    public function getRecentSavingsTransactions(int $limit = 5): array
    {
        return SavingsTransaction::with(['student.class', 'creator'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'student_name' => $transaction->student->full_name,
                    'class' => $transaction->student->class->name,
                    'amount' => (float) $transaction->amount,
                    'transaction_type' => $transaction->transaction_type,
                    'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                    'created_by' => $transaction->creator->full_name
                ];
            })
            ->toArray();
    }
}
