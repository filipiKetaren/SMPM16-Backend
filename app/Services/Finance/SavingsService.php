<?php

namespace App\Services\Finance;

use App\DTOs\SavingsTransactionData;
use App\Repositories\Interfaces\SavingsRepositoryInterface;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\log;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SavingsService extends BaseService
{
    public function __construct(
        private SavingsRepositoryInterface $savingsRepository,
        private StudentRepositoryInterface $studentRepository
    ) {}

    public function processTransaction(array $data, int $createdBy)
    {
        DB::beginTransaction();
        try {
            // Validasi data input sebelum proses DTO
            $validationResult = $this->validateRequestData($data);
            if ($validationResult['status'] === 'error') {
                return $validationResult;
            }

            $transactionData = SavingsTransactionData::fromRequest($data);

            // Validasi transaksi bisnis
            $validationResult = $this->validateTransaction($transactionData);
            if ($validationResult['status'] === 'error') {
                return $validationResult;
            }

            // Generate transaction number
            $transactionNumber = $this->generateTransactionNumber();

            // Hitung saldo sebelum dan sesudah
            $currentBalance = $this->savingsRepository->getStudentCurrentBalance($transactionData->studentId);
            $balanceBefore = $currentBalance;

            if ($transactionData->transactionType === 'deposit') {
                $balanceAfter = $balanceBefore + $transactionData->amount;
            } else {
                $balanceAfter = $balanceBefore - $transactionData->amount;
            }

            // Buat transaksi
            $transaction = $this->savingsRepository->createTransaction([
                'transaction_number' => $transactionNumber,
                'student_id' => $transactionData->studentId,
                'transaction_type' => $transactionData->transactionType,
                'amount' => $transactionData->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'transaction_date' => $transactionData->transactionDate,
                'notes' => $transactionData->notes,
                'created_by' => $createdBy,
            ]);

            DB::commit();

            $transaction->load(['student', 'creator']);

            return $this->success($transaction, 'Transaksi tabungan berhasil diproses', 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validasi gagal: ' . $e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            // Log error untuk debugging
            Log::error('Savings transaction error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
            return $this->error('Gagal memproses transaksi: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Validasi dasar data request sebelum diproses DTO
     */
    private function validateRequestData(array $data)
    {
        $requiredFields = [
            'student_id' => 'ID Siswa',
            'transaction_type' => 'Jenis Transaksi',
            'amount' => 'Jumlah Transaksi',
            'transaction_date' => 'Tanggal Transaksi'
        ];

        foreach ($requiredFields as $field => $label) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return $this->error("Field {$label} harus diisi", ['field' => $field], 422);
            }
        }

        // Validasi tipe transaksi
        if (isset($data['transaction_type']) && !in_array($data['transaction_type'], ['deposit', 'withdrawal'])) {
            return $this->error('Jenis transaksi harus deposit atau withdrawal',
            ['deposit' => 'deposit',
            'withdrawal' => 'withdrawal'],
            422);
        }

        // Validasi amount
        if (isset($data['amount']) && (!is_numeric($data['amount']) || $data['amount'] <= 0)) {
            return $this->error('Jumlah transaksi harus angka dan lebih dari 0',
            ['amount' => $data['amount']],
             422);
        }

        return $this->success(null, 'Validasi data berhasil', 200);
    }

    public function updateTransaction(int $transactionId, array $data, int $updatedBy)
    {
        DB::beginTransaction();
        try {
            $existingTransaction = $this->savingsRepository->findTransaction($transactionId);
            if (!$existingTransaction) {
                return $this->error('Transaksi tidak ditemukan', null, 404);
            }

            // Untuk update, kita perlu menghitung ulang semua transaksi setelahnya
            // Ini kompleks, jadi untuk sekarang kita batasi update hanya pada transaksi terakhir
            $lastTransaction = $this->savingsRepository->getStudentTransactions($existingTransaction->student_id)
                ->first();

            if ($lastTransaction->id !== $transactionId) {
                return $this->error('Hanya transaksi terakhir yang dapat diupdate', null, 422);
            }

            $transactionData = SavingsTransactionData::fromRequest($data);

            // Validasi
            $validationResult = $this->validateTransaction($transactionData, $existingTransaction->student_id);
            if ($validationResult['status'] === 'error') {
                return $validationResult;
            }

            // Hitung ulang saldo
            $balanceBefore = $this->savingsRepository->getStudentCurrentBalance($existingTransaction->student_id);

            if ($transactionData->transactionType === 'deposit') {
                $balanceAfter = $balanceBefore + $transactionData->amount;
            } else {
                $balanceAfter = $balanceBefore - $transactionData->amount;
            }

            // Update transaksi
            $updated = $this->savingsRepository->updateTransaction($transactionId, [
                'transaction_type' => $transactionData->transactionType,
                'amount' => $transactionData->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'transaction_date' => $transactionData->transactionDate,
                'notes' => $transactionData->notes,
            ]);

            if (!$updated) {
                return $this->error('Gagal mengupdate transaksi', null, 500);
            }

            DB::commit();

            $transaction = $this->savingsRepository->getTransactionWithDetails($transactionId);

            return $this->success($transaction, 'Transaksi tabungan berhasil diupdate', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal mengupdate transaksi: ' . $e->getMessage(), null, 500);
        }
    }

    public function deleteTransaction(int $transactionId)
    {
        DB::beginTransaction();
        try {
            $transaction = $this->savingsRepository->findTransaction($transactionId);
            if (!$transaction) {
                return $this->error('Transaksi tidak ditemukan', null, 404);
            }

            // Hanya boleh hapus transaksi terakhir
            $lastTransaction = $this->savingsRepository->getStudentTransactions($transaction->student_id)
                ->first();

            if ($lastTransaction->id !== $transactionId) {
                return $this->error('Hanya transaksi terakhir yang dapat dihapus', null, 422);
            }

            $deleted = $this->savingsRepository->deleteTransaction($transactionId);

            if (!$deleted) {
                return $this->error('Gagal menghapus transaksi', null, 500);
            }

            DB::commit();

            return $this->success(null, 'Transaksi tabungan berhasil dihapus', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal menghapus transaksi: ' . $e->getMessage(), null, 500);
        }
    }

    public function getTransactionDetail(int $transactionId)
    {
        try {
            $transaction = $this->savingsRepository->getTransactionWithDetails($transactionId);

            if (!$transaction) {
                return $this->error('Transaksi tidak ditemukan', null, 404);
            }

            return $this->success($transaction, 'Detail transaksi berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->error('Gagal mengambil detail transaksi: ' . $e->getMessage(), null, 500);
        }
    }

    public function getStudentSavings(int $studentId)
    {
        try {
            $student = $this->studentRepository->getStudentById($studentId);
            if (!$student) {
                return $this->error('Siswa tidak ditemukan', null, 404);
            }

            $transactions = $this->savingsRepository->getStudentTransactions($studentId);
            $currentBalance = $this->savingsRepository->getStudentCurrentBalance($studentId);

            $data = [
                'student' => [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'class' => $student->class->name,
                ],
                'savings' => [
                    'current_balance' => $currentBalance,
                    'total_deposits' => $transactions->where('transaction_type', 'deposit')->sum('amount'),
                    'total_withdrawals' => $transactions->where('transaction_type', 'withdrawal')->sum('amount'),
                    'transaction_history' => $transactions
                ]
            ];

            return $this->success($data, 'Data tabungan siswa berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data tabungan: ' . $e->getMessage(), null, 500);
        }
    }

    public function getAllStudentsWithSavings()
    {
        try {
            $students = $this->studentRepository->getActiveStudentsWithClassAndPayments()
                ->map(function ($student) {
                    $currentBalance = $this->savingsRepository->getStudentCurrentBalance($student->id);

                    return [
                        'student' => [
                            'id' => $student->id,
                            'nis' => $student->nis,
                            'full_name' => $student->full_name,
                            'class' => $student->class->name,
                        ],
                        'savings' => [
                            'current_balance' => $currentBalance,
                            'last_transaction_date' => $student->savingsTransactions->sortByDesc('transaction_date')->first()->transaction_date ?? null
                        ]
                    ];
                });

            return $this->success($students, 'Data tabungan semua siswa berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data tabungan: ' . $e->getMessage(), null, 500);
        }
    }

    private function validateTransaction(SavingsTransactionData $transactionData)
    {
        try {
            // Validasi student exists
            $student = $this->studentRepository->getStudentById($transactionData->studentId);
            if (!$student) {
                return $this->error('Siswa tidak ditemukan', null, 404);
            }

            // Validasi amount sudah dilakukan di DTO, tapi double check
            if ($transactionData->amount <= 0) {
                return $this->error('Jumlah transaksi harus lebih dari 0', null, 422);
            }

            // Validasi saldo untuk penarikan
            if ($transactionData->transactionType === 'withdrawal') {
                $currentBalance = $this->savingsRepository->getStudentCurrentBalance($transactionData->studentId);
                if ($transactionData->amount > $currentBalance) {
                    return $this->error('Saldo tidak cukup untuk penarikan', [
                        'current_balance' => $currentBalance,
                        'withdrawal_amount' => $transactionData->amount
                    ], 422);
                }
            }

            return $this->success(null, 'Validasi berhasil', 200);

        } catch (\Exception $e) {
            return $this->error('Validasi transaksi gagal: ' . $e->getMessage(), null, 500);
        }
    }

    private function generateTransactionNumber(): string
    {
        $count = $this->savingsRepository->getTransactionCount();
        return 'TAB/' . date('Y') . '/' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }
}
