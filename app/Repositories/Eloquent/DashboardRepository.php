<?php

namespace App\Repositories\Eloquent;

use App\Models\Student;
use App\Models\SppPayment;
use App\Models\SppPaymentDetail;
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
        // Hitung siswa yang belum bayar SPP bulan tertentu
        $paidStudentIds = SppPaymentDetail::where('year', $year)
            ->where('month', $month)
            ->join('spp_payments', 'spp_payment_details.payment_id', '=', 'spp_payments.id')
            ->pluck('spp_payments.student_id')
            ->unique()
            ->toArray();

        return Student::where('status', 'active')
            ->whereNotIn('id', $paidStudentIds)
            ->count();
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
        return SppPayment::with(['student', 'creator'])
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
            ->join('classes', 'students.class_id', '=', 'classes.id')
            ->select(
                'classes.name as class_name',
                DB::raw('COUNT(students.id) as student_count')
            )
            ->groupBy('classes.name', 'classes.id')
            ->orderBy('classes.name')
            ->get()
            ->map(function ($item) {
                return [
                    'class_name' => $item->class_name,
                    'student_count' => $item->student_count
                ];
            })
            ->toArray();
    }
}
