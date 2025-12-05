<?php

namespace App\Services\Parent;

use App\Repositories\Interfaces\ParentFinanceRepositoryInterface;
use App\Services\BaseService;
use Carbon\Carbon;
use App\Models\AcademicYear;
use App\Models\SppPayment;

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

    public function getSppHistory(int $parentId, ?string $year = null)
    {
        try {
            // Validasi tahun
            if ($year && !$this->isValidYear($year)) {
                return $this->validationError([
                    'year' => ['Format tahun tidak valid. Gunakan format YYYY.']
                ], 'Validasi tahun gagal');
            }

            // Gunakan tahun akademik aktif jika tidak ada tahun yang diminta
            if (!$year) {
                $activeAcademicYear = AcademicYear::where('is_active', true)->first();
                if ($activeAcademicYear) {
                    $year = Carbon::parse($activeAcademicYear->start_date)->year;
                } else {
                    $year = Carbon::now()->year;
                }
            }

            $currentYear = (int) $year;

            // Dapatkan student IDs yang terkait dengan parent
            $studentIds = $this->parentFinanceRepository->getStudentIdsByParent($parentId);

            if (empty($studentIds)) {
                return $this->notFoundError('Tidak ada siswa yang terdaftar untuk orang tua ini');
            }

            // Data siswa dengan info SPP
            $students = $this->getStudentsWithSppInfo($studentIds, $currentYear);

            // PERBAIKAN: Dapatkan transaksi SPP dengan rentang tahun akademik
            $transactions = [];
            $allSppPayments = collect();

            foreach ($studentIds as $studentId) {
                $student = $this->parentFinanceRepository->getStudentById($studentId);
                if ($student && $student->class && $student->class->academicYear) {
                    $academicYear = $student->class->academicYear;

                    // Dapatkan pembayaran dalam rentang tahun akademik
                    $sppPayments = SppPayment::with(['paymentDetails', 'student', 'creator'])
                        ->where('student_id', $studentId)
                        ->whereBetween('payment_date', [
                            $academicYear->start_date,
                            $academicYear->end_date
                        ])
                        ->orderBy('payment_date', 'desc')
                        ->get();

                    $allSppPayments = $allSppPayments->merge($sppPayments);
                }
            }

            // Format transaksi SPP
            $transactions = $this->formatSppTransactions($allSppPayments);

            // Hitung total SPP yang sudah dibayar
            $totalSppPaid = $allSppPayments->sum('total_amount');

            $data = [
                'period' => [
                    'year' => $currentYear,
                    'year_name' => "Tahun {$currentYear}",
                    'note' => $students[0]['academic_year_info']['name'] ?? 'Tahun akademik tidak tersedia'
                ],
                'students' => $students,
                'transactions' => $transactions,
                'summary' => $this->getSppSummary($students, $transactions)
            ];

            return $this->success($data, 'Riwayat transaksi SPP berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil riwayat transaksi SPP', $e);
        }
    }

    public function getSavingsHistory(int $parentId, ?string $year = null)
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

            // Data siswa dengan info tabungan
            $students = $this->getStudentsWithSavingsInfo($studentIds);

            // Dapatkan transaksi tabungan
            $savingsTransactions = $this->parentFinanceRepository->getSavingsTransactionsByStudentIds($studentIds, $year);

            // Format transaksi tabungan
            $transactions = $this->formatSavingsTransactions($savingsTransactions);

            $data = [
                'period' => [
                    'year' => $currentYear,
                    'year_name' => "Tahun {$currentYear}"
                ],
                'students' => $students,
                'transactions' => $transactions,
                'summary' => $this->getSavingsSummaryFromData($students, $transactions)
            ];

            return $this->success($data, 'Riwayat transaksi tabungan berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil riwayat transaksi tabungan', $e);
        }
    }

    public function getSavingsSummary(int $parentId)
    {
        try {
            // Dapatkan student IDs yang terkait dengan parent
            $studentIds = $this->parentFinanceRepository->getStudentIdsByParent($parentId);

            if (empty($studentIds)) {
                return $this->notFoundError('Tidak ada siswa yang terdaftar untuk orang tua ini');
            }

            // Data siswa dengan info tabungan
            $students = $this->getStudentsWithSavingsInfo($studentIds);

            // Dapatkan transaksi tabungan tahun ini
            $currentYear = Carbon::now()->year;
            $savingsTransactions = $this->parentFinanceRepository->getSavingsTransactionsByStudentIds($studentIds, $currentYear);

            // Format transaksi tabungan
            $transactions = $this->formatSavingsTransactions($savingsTransactions);

            $data = [
                'summary' => $this->getSavingsSummaryFromData($students, $transactions),
                'students' => array_map(function($student) {
                    return [
                        'id' => $student['id'],
                        'full_name' => $student['full_name'],
                        'class' => $student['class'],
                        'savings_balance' => $student['savings_balance'],
                        'last_transaction_date' => $student['last_transaction_date'] ?? null
                    ];
                }, $students)
            ];

            return $this->success($data, 'Ringkasan tabungan berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil ringkasan tabungan', $e);
        }
    }

    private function getStudentsWithSppInfo(array $studentIds, int $year): array
    {
        $students = [];

        foreach ($studentIds as $studentId) {
            $student = $this->parentFinanceRepository->getStudentById($studentId);
            if ($student) {
                // PERBAIKAN: Gunakan metode baru yang memberikan detail
                $unpaidMonthsDetail = $this->parentFinanceRepository->getStudentUnpaidMonthsWithDetail($studentId, $year);
                $unpaidMonths = array_column($unpaidMonthsDetail, 'month');

                $students[] = [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'class' => $student->class->name ?? 'Tidak ada kelas',
                    'grade_level' => $student->class->grade_level ?? null,
                    'unpaid_months' => $unpaidMonths,
                    'unpaid_months_detail' => $unpaidMonthsDetail, // Tambahkan detail
                    'total_spp_paid' => $this->parentFinanceRepository->getStudentTotalSppPaid($studentId, $year),
                    'academic_year_info' => $this->parentFinanceRepository->getStudentAcademicYearInfo($studentId),
                ];
            }
        }

        return $students;
    }

    private function getStudentsWithSavingsInfo(array $studentIds): array
    {
        $students = [];

        foreach ($studentIds as $studentId) {
            $student = $this->parentFinanceRepository->getStudentById($studentId);
            if ($student) {
                $lastTransaction = $this->parentFinanceRepository->getStudentLastSavingsTransaction($studentId);

                $students[] = [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'class' => $student->class->name ?? 'Tidak ada kelas',
                    'grade_level' => $student->class->grade_level ?? null,
                    'savings_balance' => $this->parentFinanceRepository->getStudentCurrentSavingsBalance($studentId),
                    'total_deposits' => $this->parentFinanceRepository->getStudentTotalSavingsDeposits($studentId),
                    'total_withdrawals' => $this->parentFinanceRepository->getStudentTotalSavingsWithdrawals($studentId),
                    'last_transaction_date' => $lastTransaction ? $lastTransaction->transaction_date->format('Y-m-d') : null,
                    'last_transaction_type' => $lastTransaction ? $lastTransaction->transaction_type : null,
                ];
            }
        }

        return $students;
    }

    private function formatSppTransactions($sppPayments): array
    {
        $transactions = [];

        foreach ($sppPayments as $payment) {
            foreach ($payment->paymentDetails as $detail) {
                $transactions[] = [
                    'id' => $detail->id,
                    'payment_id' => $payment->id,
                    'payment_detail_id' => $detail->id,
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

        // Urutkan berdasarkan tanggal transaksi (terbaru pertama)
        usort($transactions, function($a, $b) {
            return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
        });

        return $transactions;
    }

    private function formatSavingsTransactions($savingsTransactions): array
    {
        $transactions = [];

        foreach ($savingsTransactions as $transaction) {
            $transactions[] = [
                'id' => $transaction->id,
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

    private function getSppSummary(array $students, array $transactions): array
    {
        $totalSpp = 0;
        $totalPaidMonths = 0;
        $totalUnpaidMonths = 0;

        // Hitung total akademik months dari semua siswa
        $totalAcademicMonths = 0;

        foreach ($transactions as $transaction) {
            if ($transaction['type'] === 'spp_payment') {
                $totalSpp += $transaction['amount'];
                $totalPaidMonths++;
            }
        }

        foreach ($students as $student) {
            $totalUnpaidMonths += count($student['unpaid_months'] ?? []);

            // Hitung total bulan akademik untuk siswa ini
            if (isset($student['academic_year_info'])) {
                $academicYear = $student['academic_year_info'];
                $start = Carbon::parse($academicYear['start_date']);
                $end = Carbon::parse($academicYear['end_date']);
                $totalAcademicMonths += $start->diffInMonths($end) + 1; // +1 untuk inklusif
            }
        }

        $averagePayment = $totalPaidMonths > 0 ? $totalSpp / $totalPaidMonths : 0;
        $paymentPercentage = $totalAcademicMonths > 0 ?
            round(($totalPaidMonths / $totalAcademicMonths) * 100, 2) : 0;

        return [
            'total_students' => count($students),
            'total_spp_paid' => $totalSpp,
            'total_paid_months' => $totalPaidMonths,
            'total_unpaid_months' => $totalUnpaidMonths,
            'total_academic_months' => $totalAcademicMonths,
            'payment_percentage' => $paymentPercentage,
            'average_payment_per_month' => round($averagePayment, 2),
            'total_transactions' => count($transactions),
        ];
    }

    private function getSavingsSummaryFromData(array $students, array $transactions): array
    {
        $totalSavingsBalance = 0;
        $totalDeposits = 0;
        $totalWithdrawals = 0;

        foreach ($students as $student) {
            $totalSavingsBalance += $student['savings_balance'] ?? 0;
        }

        foreach ($transactions as $transaction) {
            if ($transaction['type'] === 'savings_deposit') {
                $totalDeposits += $transaction['amount'];
            } elseif ($transaction['type'] === 'savings_withdrawal') {
                $totalWithdrawals += $transaction['amount'];
            }
        }

        return [
            'total_students' => count($students),
            'total_savings_balance' => $totalSavingsBalance,
            'total_deposits' => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'net_savings_change' => $totalDeposits - $totalWithdrawals,
            'total_transactions' => count($transactions),
            'average_balance_per_student' => count($students) > 0 ? round($totalSavingsBalance / count($students), 2) : 0,
        ];
    }

    // ================== METHOD HELPER YANG SUDAH ADA ==================

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
                // PERBAIKAN: Pisah id, payment_id, dan payment_detail_id
                $transactions[] = [
                    'id' => $detail->id, // Hanya id detail
                    'payment_id' => $payment->id, // ID pembayaran terpisah
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
            // PERBAIKAN: Hanya gunakan id transaksi
            $transactions[] = [
                'id' => $transaction->id, // Hanya id transaksi
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
