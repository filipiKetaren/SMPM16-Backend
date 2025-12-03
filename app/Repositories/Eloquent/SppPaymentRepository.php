<?php

namespace App\Repositories\Eloquent;

use App\Models\SppPayment;
use App\Models\AcademicYear;
use App\Repositories\Interfaces\SppPaymentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SppPaymentRepository implements SppPaymentRepositoryInterface
{
    public function createPayment(array $data): SppPayment
    {
        return SppPayment::create($data);
    }

    public function getPaymentsByStudentAndYear(int $studentId, int $year): Collection
    {
        return SppPayment::where('student_id', $studentId)
            ->with(['paymentDetails' => function($query) use ($year) {
                $query->where('year', $year);
            }])
            ->get();
    }

    public function getStudentPaidMonths(int $studentId, int $year): array
    {
        // Tetap pertahankan untuk kompatibilitas
        $payments = $this->getPaymentsByStudentAndYear($studentId, $year);

        $paidMonths = [];
        foreach ($payments as $payment) {
            foreach ($payment->paymentDetails as $detail) {
                if ($detail->year == $year) {
                    $paidMonths[] = $detail->month;
                }
            }
        }

        return $paidMonths;
    }

    public function getPaymentCount(): int
    {
        return SppPayment::count();
    }

    public function getPaymentWithDetails(int $paymentId)
    {
        return SppPayment::with(['student', 'creator', 'paymentDetails'])->find($paymentId);
    }

    public function updatePayment(int $paymentId, array $data): bool
    {
        $payment = SppPayment::find($paymentId);
        if (!$payment) {
            return false;
        }

        return $payment->update($data);
    }

    public function deletePayment(int $paymentId): bool
    {
        $payment = SppPayment::find($paymentId);
        if (!$payment) {
            return false;
        }

        return $payment->delete();
    }

    public function findPayment(int $paymentId)
    {
        return SppPayment::find($paymentId);
    }

    public function getPaymentByReceiptNumber(string $receiptNumber)
    {
        return SppPayment::where('receipt_number', $receiptNumber)->first();
    }

    public function getLastPaymentByStudent(int $studentId): ?SppPayment
    {
        return SppPayment::where('student_id', $studentId)
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    public function getStudentPaymentCount(int $studentId): int
    {
        return SppPayment::where('student_id', $studentId)->count();
    }

    public function getLatestPaymentByStudent(int $studentId): ?SppPayment
    {
        return SppPayment::where('student_id', $studentId)
            ->with(['paymentDetails' => function($query) {
                $query->orderBy('year', 'desc')
                      ->orderBy('month', 'desc');
            }])
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    public function getPaymentsByStudentAndAcademicYear(int $studentId, int $academicYearId): Collection
    {
        // Dapatkan tahun akademik
        $academicYear = AcademicYear::find($academicYearId);
        if (!$academicYear) {
            return new Collection();
        }

        // Ambil pembayaran dalam rentang tanggal tahun akademik
        return SppPayment::where('student_id', $studentId)
            ->whereBetween('payment_date', [
                $academicYear->start_date,
                $academicYear->end_date
            ])
            ->with(['paymentDetails'])
            ->get();
    }

    public function getStudentPaidAcademicMonthsWithYear(int $studentId, int $academicYearId): array
    {
        // Ambil pembayaran siswa dan filter yang sesuai dengan tahun akademik
        $payments = $this->getPaymentsByStudentAndAcademicYear($studentId, $academicYearId);

        $paidMonths = [];
        foreach ($payments as $payment) {
            foreach ($payment->paymentDetails as $detail) {
                $paidMonths[] = [
                    'month' => $detail->month,
                    'year' => $detail->year,
                    'amount' => (float) $detail->amount,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'receipt_number' => $payment->receipt_number,
                ];
            }
        }

        return $paidMonths;
    }
}
