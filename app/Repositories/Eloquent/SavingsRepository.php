<?php

namespace App\Repositories\Eloquent;

use App\Models\SavingsTransaction;
use App\Repositories\Interfaces\SavingsRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SavingsRepository implements SavingsRepositoryInterface
{
    public function createTransaction(array $data): SavingsTransaction
    {
        return SavingsTransaction::create($data);
    }

    public function updateTransaction(int $transactionId, array $data): bool
    {
        $transaction = SavingsTransaction::find($transactionId);
        if (!$transaction) {
            return false;
        }

        return $transaction->update($data);
    }

    public function deleteTransaction(int $transactionId): bool
    {
        $transaction = SavingsTransaction::find($transactionId);
        if (!$transaction) {
            return false;
        }

        return $transaction->delete();
    }

    public function findTransaction(int $transactionId)
    {
        return SavingsTransaction::find($transactionId);
    }

    public function getTransactionWithDetails(int $transactionId)
    {
        return SavingsTransaction::with(['student', 'creator'])->find($transactionId);
    }

    public function getStudentTransactions(int $studentId)
    {
        return SavingsTransaction::with(['creator'])
            ->where('student_id', $studentId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getStudentCurrentBalance(int $studentId): float
    {
        $lastTransaction = SavingsTransaction::where('student_id', $studentId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastTransaction ? (float) $lastTransaction->balance_after : 0;
    }

    public function getTransactionCount(): int
    {
        return SavingsTransaction::count();
    }

    public function getRecentTransactions(int $limit = 10): Collection
    {
        return SavingsTransaction::with(['student', 'creator'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getTransactionsByDateRange(string $startDate, string $endDate): Collection
    {
        return SavingsTransaction::with(['student', 'creator'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}
