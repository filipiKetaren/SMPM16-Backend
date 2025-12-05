<?php

namespace App\Repositories\Interfaces;

interface ParentFinanceRepositoryInterface
{
    public function getStudentIdsByParent(int $parentId): array;
    public function getSppPaymentsByStudentIds(array $studentIds, ?string $year = null);
    public function getSavingsTransactionsByStudentIds(array $studentIds, ?string $year = null);
    public function getStudentCurrentSavingsBalance(int $studentId): float;
    public function getStudentUnpaidMonths(int $studentId, int $year): array;
    public function getStudentById(int $studentId);
    public function getStudentTotalSppPaid(int $studentId, int $year): float;
    public function getStudentLastSavingsTransaction(int $studentId);
    public function getStudentTotalSavingsDeposits(int $studentId): float;
    public function getStudentTotalSavingsWithdrawals(int $studentId): float;
    public function getStudentSavingsTransactionsByMonth(int $studentId, int $year, int $month);
    public function getStudentUnpaidMonthsWithDetail(int $studentId, int $year): array;
    public function getStudentAcademicYearInfo(int $studentId): ?array;
}
