<?php

namespace App\Services\Finance;

use App\DTOs\SavingsTransactionData;
use App\Repositories\Interfaces\SavingsRepositoryInterface;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            // ⚠️ PERBAIKAN: Validasi required fields TERLEBIH DAHULU
            $validationResult = $this->validateTransactionRequiredFields($data);
            if ($validationResult) {
                return $validationResult;
            }

            // Coba buat DTO, jika ada ValidationException, tangkap dan format ulang
            try {
                $transactionData = SavingsTransactionData::fromRequest($data);
            } catch (ValidationException $e) {
                return $this->validationError($e->errors(), 'Validasi data transaksi gagal');
            } catch (\Exception $e) {
                return $this->serverError('Gagal memproses data transaksi', $e);
            }

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

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Savings transaction error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
            return $this->serverError('Gagal memproses transaksi tabungan', $e);
        }
    }

    /**
     * Validasi required fields untuk transaksi tabungan
     */
    private function validateTransactionRequiredFields(array $data)
    {
        $errors = [];
        $requiredFields = [
            'student_id' => 'Siswa',
            'transaction_type' => 'Jenis Transaksi',
            'amount' => 'Jumlah Transaksi',
            'transaction_date' => 'Tanggal Transaksi'
        ];

        foreach ($requiredFields as $field => $label) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = ["{$label} harus diisi"];
            }
        }

        // Validasi tipe data numerik
        if (isset($data['student_id']) && !is_numeric($data['student_id'])) {
            $errors['student_id'] = ['ID Siswa harus berupa angka'];
        }

        if (isset($data['amount']) && !is_numeric($data['amount'])) {
            $errors['amount'] = ['Jumlah transaksi harus berupa angka'];
        }

        if (isset($data['amount']) && is_numeric($data['amount']) && $data['amount'] <= 0) {
            $errors['amount'] = ['Jumlah transaksi harus lebih dari 0'];
        }

        // Validasi tipe transaksi
        if (isset($data['transaction_type']) && !in_array($data['transaction_type'], ['deposit', 'withdrawal'])) {
            $errors['transaction_type'] = ['Jenis transaksi harus deposit atau withdrawal'];
        }

        // Validasi format tanggal
        if (isset($data['transaction_date']) && $data['transaction_date'] !== '') {
            if (!strtotime($data['transaction_date'])) {
                $errors['transaction_date'] = ['Format tanggal transaksi tidak valid'];
            }
        }

        if (!empty($errors)) {
            return $this->validationError($errors, 'Data transaksi tidak lengkap');
        }

        return null;
    }

    public function updateTransaction(int $transactionId, array $data, int $updatedBy)
    {
        DB::beginTransaction();
        try {
            // ⚠️ PERBAIKAN: Validasi required fields untuk update
            $validationResult = $this->validateTransactionRequiredFields($data);
            if ($validationResult) {
                return $validationResult;
            }

            $existingTransaction = $this->savingsRepository->findTransaction($transactionId);
            if (!$existingTransaction) {
                return $this->notFoundError('Transaksi tidak ditemukan');
            }

            // Untuk update, kita perlu menghitung ulang semua transaksi setelahnya
            // Ini kompleks, jadi untuk sekarang kita batasi update hanya pada transaksi terakhir
            $lastTransaction = $this->savingsRepository->getStudentTransactions($existingTransaction->student_id)
                ->first();

            if ($lastTransaction->id !== $transactionId) {
                return $this->validationError(
                    ['transaction_id' => ['Hanya transaksi terakhir yang dapat diupdate']],
                    'Transaksi tidak dapat diupdate'
                );
            }

            // Coba buat DTO
            try {
                $transactionData = SavingsTransactionData::fromRequest($data);
            } catch (ValidationException $e) {
                return $this->validationError($e->errors(), 'Validasi data transaksi gagal');
            }

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
            return $this->serverError('Gagal mengupdate transaksi tabungan', $e);
        }
    }

    public function deleteTransaction(int $transactionId)
    {
        DB::beginTransaction();
        try {
            $transaction = $this->savingsRepository->findTransaction($transactionId);
            if (!$transaction) {
                return $this->notFoundError('Transaksi tidak ditemukan');
            }

            // Hanya boleh hapus transaksi terakhir
            $lastTransaction = $this->savingsRepository->getStudentTransactions($transaction->student_id)
                ->first();

            if ($lastTransaction->id !== $transactionId) {
                return $this->validationError(
                    ['transaction_id' => ['Hanya transaksi terakhir yang dapat dihapus']],
                    'Transaksi tidak dapat dihapus'
                );
            }

            $deleted = $this->savingsRepository->deleteTransaction($transactionId);

            if (!$deleted) {
                return $this->error('Gagal menghapus transaksi', null, 500);
            }

            DB::commit();

            return $this->success(null, 'Transaksi tabungan berhasil dihapus', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal menghapus transaksi tabungan', $e);
        }
    }

    public function getTransactionDetail(int $transactionId)
    {
        try {
            $transaction = $this->savingsRepository->getTransactionWithDetails($transactionId);

            if (!$transaction) {
                return $this->notFoundError('Transaksi tidak ditemukan');
            }

            return $this->success($transaction, 'Detail transaksi berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil detail transaksi tabungan', $e);
        }
    }

    public function getStudentSavings(int $studentId)
    {
        try {
            $student = $this->studentRepository->getStudentById($studentId);
            if (!$student) {
                return $this->notFoundError('Siswa tidak ditemukan');
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
            return $this->serverError('Gagal mengambil data tabungan siswa', $e);
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
            return $this->serverError('Gagal mengambil data tabungan semua siswa', $e);
        }
    }

    /**
     * Validasi bisnis untuk transaksi tabungan
     */
    private function validateTransaction(SavingsTransactionData $transactionData, ?int $studentId = null)
    {
        $targetStudentId = $studentId ?? $transactionData->studentId;

        // Validasi student exists
        $student = $this->studentRepository->getStudentById($targetStudentId);
        if (!$student) {
            return $this->notFoundError('Siswa tidak ditemukan');
        }

        // Validasi saldo untuk penarikan
        if ($transactionData->transactionType === 'withdrawal') {
            $currentBalance = $this->savingsRepository->getStudentCurrentBalance($targetStudentId);
            if ($transactionData->amount > $currentBalance) {
                return $this->validationError(
                    [
                        'amount' => [
                            'Saldo tidak cukup untuk penarikan. Saldo saat ini: Rp ' .
                            number_format($currentBalance, 0, ',', '.') .
                            ', Jumlah penarikan: Rp ' . number_format($transactionData->amount, 0, ',', '.')
                        ]
                    ],
                    'Saldo tidak mencukupi'
                );
            }
        }

        return $this->success(null, 'Validasi transaksi berhasil', 200);
    }

    private function generateTransactionNumber(): string
    {
        $count = $this->savingsRepository->getTransactionCount();
        return 'TAB/' . date('Y') . '/' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }
}
