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
}
