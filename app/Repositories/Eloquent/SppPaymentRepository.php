<?php

namespace App\Repositories\Eloquent;

use App\Models\SppPayment;
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
}
