<?php

namespace App\Repositories\Interfaces;

use App\Models\SppPayment;
use Illuminate\Database\Eloquent\Collection;

interface SppPaymentRepositoryInterface
{
    public function createPayment(array $data): SppPayment;
    public function getPaymentsByStudentAndYear(int $studentId, int $year): Collection;
    public function getStudentPaidMonths(int $studentId, int $year): array;
    public function getPaymentCount(): int;
    public function getPaymentWithDetails(int $paymentId);
    public function updatePayment(int $paymentId, array $data): bool;
    public function deletePayment(int $paymentId): bool;
    public function findPayment(int $paymentId);
    public function getPaymentByReceiptNumber(string $receiptNumber);
}
