<?php

namespace App\Repositories\Interfaces;

use App\Models\SavingsTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface SavingsRepositoryInterface
{
    public function createTransaction(array $data): SavingsTransaction;
    public function updateTransaction(int $transactionId, array $data): bool;
    public function deleteTransaction(int $transactionId): bool;
    public function findTransaction(int $transactionId);
    public function getTransactionWithDetails(int $transactionId);
    public function getStudentTransactions(int $studentId);
    public function getStudentCurrentBalance(int $studentId): float;
    public function getTransactionCount(): int;
    public function getRecentTransactions(int $limit = 10): Collection;
    public function getTransactionsByDateRange(string $startDate, string $endDate): Collection;
    public function getStudentsWithSavingsPaginated(array $filters = [], int $perPage = 5): LengthAwarePaginator;
}
