<?php

namespace App\Repositories\Eloquent;

use App\Models\Student;
use App\Models\SppPayment;
use App\Models\SppPaymentDetail;
use App\Models\SavingsTransaction;
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
            'student.class',
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

    public function getStudentUnpaidMonths(int $studentId, int $year): array
    {
        // Dapatkan bulan yang sudah dibayar
        $paidMonths = SppPaymentDetail::whereHas('payment', function($query) use ($studentId, $year) {
            $query->where('student_id', $studentId)
                  ->whereYear('payment_date', $year);
        })
        ->where('year', $year)
        ->pluck('month')
        ->toArray();

        // Semua bulan
        $allMonths = range(1, 12);

        // Bulan yang belum dibayar
        $unpaidMonths = array_diff($allMonths, $paidMonths);

        return array_values($unpaidMonths);
    }

    public function getStudentById(int $studentId)
    {
        return Student::with('class')->find($studentId);
    }
}
