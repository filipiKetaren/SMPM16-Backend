<?php

namespace App\Services\Parent;

use App\Repositories\Interfaces\ParentFinanceRepositoryInterface;
use App\Services\BaseService;
use Carbon\Carbon;

class ParentFinanceService extends BaseService
{
    public function __construct(
        private ParentFinanceRepositoryInterface $parentFinanceRepository
    ) {}

    public function getFinanceHistory(int $parentId, ?string $year = null)
    {
        try {
            // Validasi tahun
            if ($year && !$this->isValidYear($year)) {
                return $this->validationError([
                    'year' => ['Format tahun tidak valid. Gunakan format YYYY.']
                ], 'Validasi tahun gagal');
            }

            $currentYear = $year ? (int) $year : Carbon::now()->year;

            // Dapatkan student IDs yang terkait dengan parent
            $studentIds = $this->parentFinanceRepository->getStudentIdsByParent($parentId);

            if (empty($studentIds)) {
                return $this->notFoundError('Tidak ada siswa yang terdaftar untuk orang tua ini');
            }

            // Data siswa dengan info lengkap
            $students = $this->getStudentsWithFinanceInfo($studentIds, $currentYear);

            // Riwayat transaksi gabungan
            $transactions = $this->getCombinedTransactions($studentIds, $currentYear);

            $data = [
                'period' => [
                    'year' => $currentYear,
                    'year_name' => "Tahun {$currentYear}"
                ],
                'students' => $students,
                'transactions' => $transactions,
                'summary' => $this->getFinanceSummary($students, $transactions)
            ];

            return $this->success($data, 'Riwayat transaksi keuangan berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil riwayat transaksi keuangan', $e);
        }
    }

    public function getStudentFinanceDetail(int $parentId, int $studentId, ?string $year = null)
    {
        try {
            // Validasi tahun
            if ($year && !$this->isValidYear($year)) {
                return $this->validationError([
                    'year' => ['Format tahun tidak valid. Gunakan format YYYY.']
                ], 'Validasi tahun gagal');
            }

            $currentYear = $year ? (int) $year : Carbon::now()->year;

            // Validasi bahwa student memang milik parent
            $studentIds = $this->parentFinanceRepository->getStudentIdsByParent($parentId);

            if (!in_array($studentId, $studentIds)) {
                return $this->notFoundError('Siswa tidak ditemukan atau tidak terdaftar untuk orang tua ini');
            }

            // Data siswa
            $student = $this->parentFinanceRepository->getStudentById($studentId);
            if (!$student) {
                return $this->notFoundError('Data siswa tidak ditemukan');
            }

            // Data transaksi untuk siswa spesifik
            $sppPayments = $this->parentFinanceRepository->getSppPaymentsByStudentIds([$studentId], $year);
            $savingsTransactions = $this->parentFinanceRepository->getSavingsTransactionsByStudentIds([$studentId], $year);

            // Gabungkan dan urutkan transaksi
            $transactions = $this->formatCombinedTransactions($sppPayments, $savingsTransactions);

            // Info keuangan siswa
            $studentFinance = [
                'student' => [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'class' => $student->class->name ?? 'Tidak ada kelas',
                    'grade_level' => $student->class->grade_level ?? null,
                ],
                'savings_balance' => $this->parentFinanceRepository->getStudentCurrentSavingsBalance($studentId),
                'unpaid_months' => $this->parentFinanceRepository->getStudentUnpaidMonths($studentId, $currentYear),
                'spp_payments_count' => $sppPayments->count(),
                'savings_transactions_count' => $savingsTransactions->count(),
            ];

            $data = [
                'period' => [
                    'year' => $currentYear,
                    'year_name' => "Tahun {$currentYear}"
                ],
                'student_finance' => $studentFinance,
                'transactions' => $transactions
            ];

            return $this->success($data, 'Detail transaksi keuangan siswa berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil detail transaksi keuangan siswa', $e);
        }
    }

    private function getStudentsWithFinanceInfo(array $studentIds, int $year): array
    {
        $students = [];

        foreach ($studentIds as $studentId) {
            $student = $this->parentFinanceRepository->getStudentById($studentId);
            if ($student) {
                $students[] = [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'class' => $student->class->name ?? 'Tidak ada kelas',
                    'grade_level' => $student->class->grade_level ?? null,
                    'savings_balance' => $this->parentFinanceRepository->getStudentCurrentSavingsBalance($studentId),
                    'unpaid_months' => $this->parentFinanceRepository->getStudentUnpaidMonths($studentId, $year),
                ];
            }
        }

        return $students;
    }

    private function getCombinedTransactions(array $studentIds, int $year): array
    {
        $sppPayments = $this->parentFinanceRepository->getSppPaymentsByStudentIds($studentIds, $year);
        $savingsTransactions = $this->parentFinanceRepository->getSavingsTransactionsByStudentIds($studentIds, $year);

        return $this->formatCombinedTransactions($sppPayments, $savingsTransactions);
    }

    private function formatCombinedTransactions($sppPayments, $savingsTransactions): array
    {
        $transactions = [];

        // Format SPP payments
        foreach ($sppPayments as $payment) {
            foreach ($payment->paymentDetails as $detail) {
                $transactions[] = [
                    'id' => 'spp_' . $payment->id . '_' . $detail->id,
                    'type' => 'spp_payment',
                    'student_id' => $payment->student_id,
                    'student_name' => $payment->student->full_name,
                    'class' => $payment->student->class->name ?? 'Tidak ada kelas',
                    'receipt_number' => $payment->receipt_number,
                    'description' => "Pembayaran SPP Bulan " . \App\Helpers\DateHelper::getMonthName($detail->month) . " {$detail->year}",
                    'amount' => (float) $detail->amount,
                    'transaction_date' => $payment->payment_date->format('Y-m-d'),
                    'created_by' => $payment->creator->full_name ?? 'System',
                    'details' => [
                        'month' => $detail->month,
                        'year' => $detail->year,
                        'month_name' => \App\Helpers\DateHelper::getMonthName($detail->month),
                        'payment_method' => $payment->payment_method,
                        'notes' => $payment->notes
                    ]
                ];
            }
        }

        // Format savings transactions
        foreach ($savingsTransactions as $transaction) {
            $transactions[] = [
                'id' => 'savings_' . $transaction->id,
                'type' => 'savings_' . $transaction->transaction_type,
                'student_id' => $transaction->student_id,
                'student_name' => $transaction->student->full_name,
                'class' => $transaction->student->class->name ?? 'Tidak ada kelas',
                'receipt_number' => $transaction->transaction_number,
                'description' => $transaction->transaction_type === 'deposit' ? 'Setoran Tabungan' : 'Penarikan Tabungan',
                'amount' => (float) $transaction->amount,
                'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                'created_by' => $transaction->creator->full_name ?? 'System',
                'details' => [
                    'transaction_type' => $transaction->transaction_type,
                    'balance_before' => (float) $transaction->balance_before,
                    'balance_after' => (float) $transaction->balance_after,
                    'notes' => $transaction->notes
                ]
            ];
        }

        // Urutkan berdasarkan tanggal transaksi (terbaru pertama)
        usort($transactions, function($a, $b) {
            return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
        });

        return $transactions;
    }

    private function getFinanceSummary(array $students, array $transactions): array
    {
        $totalSpp = 0;
        $totalSavingsDeposits = 0;
        $totalSavingsWithdrawals = 0;

        foreach ($transactions as $transaction) {
            if ($transaction['type'] === 'spp_payment') {
                $totalSpp += $transaction['amount'];
            } elseif ($transaction['type'] === 'savings_deposit') {
                $totalSavingsDeposits += $transaction['amount'];
            } elseif ($transaction['type'] === 'savings_withdrawal') {
                $totalSavingsWithdrawals += $transaction['amount'];
            }
        }

        $totalSavingsBalance = array_sum(array_column($students, 'savings_balance'));

        return [
            'total_students' => count($students),
            'total_spp_paid' => $totalSpp,
            'total_savings_deposits' => $totalSavingsDeposits,
            'total_savings_withdrawals' => $totalSavingsWithdrawals,
            'total_savings_balance' => $totalSavingsBalance,
            'total_transactions' => count($transactions),
        ];
    }

    private function isValidYear(string $year): bool
    {
        return preg_match('/^\d{4}$/', $year) && $year >= 2020 && $year <= 2030;
    }
}
