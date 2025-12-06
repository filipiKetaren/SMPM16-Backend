<?php

namespace App\Services\Parent;

use App\Repositories\Interfaces\ParentFinanceRepositoryInterface;
use App\Helpers\DateHelper;
use App\Services\BaseService;
use Carbon\Carbon;
use App\Models\AcademicYear;
use App\Models\SppPayment;
use Illuminate\Support\Facades\Log;
use App\Models\SavingsTransaction;
use App\Helpers\FinanceHelper;

class ParentFinanceService extends BaseService
{
    public function __construct(
        private ParentFinanceRepositoryInterface $parentFinanceRepository
    ) {}

    public function getFinanceHistory(
        int $parentId,
        ?string $year = null,
        ?string $month = null,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        try {
            // Validasi parameter menggunakan FinanceHelper
            $validation = FinanceHelper::validateFilterParams($year, $month, $startDate, $endDate);
            if ($validation !== null) {
                return $validation;
            }

            if ($startDate && $endDate) {
                list($startDate, $endDate) = FinanceHelper::normalizeDateRange($startDate, $endDate);
            }

            // Dapatkan student IDs yang terkait dengan parent
            $studentIds = $this->parentFinanceRepository->getStudentIdsByParent($parentId);

            if (empty($studentIds)) {
                return $this->notFoundError('Tidak ada siswa yang terdaftar untuk orang tua ini');
            }

            // PERBAIKAN: Gunakan filter untuk mendapatkan data siswa
            $students = $this->getStudentsWithFinanceInfo($studentIds, $year, $month, $startDate, $endDate);

            // Riwayat transaksi gabungan dengan filter
            $transactions = $this->getCombinedTransactionsWithFilters(
                $studentIds,
                $year,
                $month,
                $startDate,
                $endDate
            );

            // Tentukan periode berdasarkan filter menggunakan FinanceHelper
            $periodInfo = FinanceHelper::getPeriodInfo($year, $month, $startDate, $endDate);

            $data = [
                'period' => $periodInfo,
                'filters_applied' => FinanceHelper::getAppliedFilters($year, $month, $startDate, $endDate),
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
            // Validasi tahun menggunakan FinanceHelper
            if ($year && !FinanceHelper::isValidYear($year)) {
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

    public function getSppHistory(
        int $parentId,
        ?string $year = null,
        ?string $month = null,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        try {
            // Validasi parameter menggunakan FinanceHelper
            $validation = FinanceHelper::validateFilterParams($year, $month, $startDate, $endDate);
            if ($validation !== null) {
                return $validation;
            }

            if ($startDate && $endDate) {
                list($startDate, $endDate) = FinanceHelper::normalizeDateRange($startDate, $endDate);
            }

            // Dapatkan student IDs yang terkait dengan parent
            $studentIds = $this->parentFinanceRepository->getStudentIdsByParent($parentId);

            if (empty($studentIds)) {
                return $this->notFoundError('Tidak ada siswa yang terdaftar untuk orang tua ini');
            }

            // Data siswa dengan info SPP
            $students = [];
            $currentYear = $year ? (int) $year : Carbon::now()->year;

            foreach ($studentIds as $studentId) {
                $student = $this->parentFinanceRepository->getStudentById($studentId);
                if ($student) {
                    $academicYear = $this->getStudentAcademicYear($student);

                    if ($academicYear) {
                        // Gunakan FinanceHelper untuk menghitung bulan belum dibayar dengan filter
                        $unpaidMonthsDetail = FinanceHelper::calculateUnpaidMonthsWithFilters(
                            $studentId,
                            $academicYear,
                            $year,
                            $month,
                            $startDate,
                            $endDate
                        );

                        $unpaidMonths = array_column($unpaidMonthsDetail, 'month');

                        // Hitung total SPP yang sudah dibayar dengan filter
                        $totalSppPaid = $this->parentFinanceRepository->getStudentTotalSppPaidWithFilters(
                            $studentId,
                            $year,
                            $month,
                            $startDate,
                            $endDate
                        );

                        $students[] = [
                            'id' => $student->id,
                            'nis' => $student->nis,
                            'full_name' => $student->full_name,
                            'class' => $student->class->name ?? 'Tidak ada kelas',
                            'grade_level' => $student->class->grade_level ?? null,
                            'unpaid_months' => $unpaidMonths,
                            'unpaid_months_detail' => $unpaidMonthsDetail,
                            'total_spp_paid' => $totalSppPaid,
                            'academic_year_info' => $academicYear ? [
                                'id' => $academicYear->id,
                                'name' => $academicYear->name,
                                'start_date' => $academicYear->start_date->format('Y-m-d'),
                                'end_date' => $academicYear->end_date->format('Y-m-d'),
                                'is_active' => $academicYear->is_active
                            ] : null,
                        ];
                    } else {
                        $students[] = [
                            'id' => $student->id,
                            'nis' => $student->nis,
                            'full_name' => $student->full_name,
                            'class' => $student->class->name ?? 'Tidak ada kelas',
                            'grade_level' => $student->class->grade_level ?? null,
                            'unpaid_months' => [],
                            'unpaid_months_detail' => [],
                            'total_spp_paid' => 0,
                            'academic_year_info' => null,
                        ];
                    }
                }
            }

            // Dapatkan transaksi SPP dengan filter
            $sppPayments = $this->parentFinanceRepository->getSppPaymentsByStudentIdsWithFilters(
                $studentIds,
                $year,
                $month,
                $startDate,
                $endDate
            );

            $transactions = $this->formatSppTransactions($sppPayments, $year, $month, $startDate, $endDate);

            // Tentukan periode berdasarkan filter menggunakan FinanceHelper
            $periodInfo = FinanceHelper::getPeriodInfo($year, $month, $startDate, $endDate);

            $data = [
                'period' => $periodInfo,
                'filters_applied' => FinanceHelper::getAppliedFilters($year, $month, $startDate, $endDate),
                'students' => $students,
                'transactions' => $transactions,
                'summary' => $this->getSppSummary($students, $transactions)
            ];

            return $this->success($data, 'Riwayat transaksi SPP berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil riwayat transaksi SPP', $e);
        }
    }

    /**
     * Helper method: Dapatkan tahun akademik untuk siswa
     */
    private function getStudentAcademicYear($student): ?AcademicYear
    {
        // Prioritaskan tahun akademik aktif
        $activeAcademicYear = AcademicYear::where('is_active', true)->first();
        if ($activeAcademicYear) {
            return $activeAcademicYear;
        }

        // Jika tidak ada tahun akademik aktif, gunakan dari kelas siswa
        if ($student->class && $student->class->academicYear) {
            return $student->class->academicYear;
        }

        return null;
    }

    public function getSavingsHistory(
        int $parentId,
        ?string $year = null,
        ?string $month = null,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        try {
            // Validasi parameter menggunakan FinanceHelper
            $validation = FinanceHelper::validateFilterParams($year, $month, $startDate, $endDate);
            if ($validation !== null) {
                return $validation;
            }

            if ($startDate && $endDate) {
                list($startDate, $endDate) = FinanceHelper::normalizeDateRange($startDate, $endDate);
            }

            // Dapatkan student IDs yang terkait dengan parent
            $studentIds = $this->parentFinanceRepository->getStudentIdsByParent($parentId);

            if (empty($studentIds)) {
                return $this->notFoundError('Tidak ada siswa yang terdaftar untuk orang tua ini');
            }

            // Data siswa dengan info tabungan
            $students = $this->getStudentsWithSavingsInfo($studentIds);

            // Dapatkan transaksi tabungan dengan filter
            $savingsTransactions = $this->parentFinanceRepository->getSavingsTransactionsByStudentIdsWithFilters(
                $studentIds,
                $year,
                $month,
                $startDate,
                $endDate
            );

            // Format transaksi tabungan
            $transactions = $this->formatSavingsTransactions($savingsTransactions, $year, $month, $startDate, $endDate);

            // Tentukan periode berdasarkan filter menggunakan FinanceHelper
            $periodInfo = FinanceHelper::getPeriodInfo($year, $month, $startDate, $endDate);

            $data = [
                'period' => $periodInfo,
                'filters_applied' => FinanceHelper::getAppliedFilters($year, $month, $startDate, $endDate),
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
                    'unpaid_months_detail' => $unpaidMonthsDetail,
                    'total_spp_paid' => $this->parentFinanceRepository->getStudentTotalSppPaid($studentId, $year),
                    'academic_year_info' => $this->parentFinanceRepository->getStudentAcademicYearInfo($studentId),
                ];
            }
        }

        return $students;
    }

    private function getStudentsWithSavingsInfo(array $studentIds, ?string $year = null, ?string $month = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $students = [];

        foreach ($studentIds as $studentId) {
            $student = $this->parentFinanceRepository->getStudentById($studentId);
            if ($student) {
                $lastTransaction = $this->parentFinanceRepository->getStudentLastSavingsTransaction($studentId);

                // Hitung total deposit dan withdrawal dengan filter
                $totalDepositsWithFilter = 0;
                $totalWithdrawalsWithFilter = 0;

                if ($startDate && $endDate) {
                    // Hitung dengan filter tanggal
                    $filteredTransactions = SavingsTransaction::where('student_id', $studentId)
                        ->whereDate('transaction_date', '>=', $startDate)
                        ->whereDate('transaction_date', '<=', $endDate)
                        ->get();

                    $totalDepositsWithFilter = $filteredTransactions->where('transaction_type', 'deposit')->sum('amount');
                    $totalWithdrawalsWithFilter = $filteredTransactions->where('transaction_type', 'withdrawal')->sum('amount');
                } else {
                    $totalDepositsWithFilter = $this->parentFinanceRepository->getStudentTotalSavingsDeposits($studentId);
                    $totalWithdrawalsWithFilter = $this->parentFinanceRepository->getStudentTotalSavingsWithdrawals($studentId);
                }

                $students[] = [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'class' => $student->class->name ?? 'Tidak ada kelas',
                    'grade_level' => $student->class->grade_level ?? null,
                    'savings_balance' => $this->parentFinanceRepository->getStudentCurrentSavingsBalance($studentId),
                    'total_deposits' => $totalDepositsWithFilter,
                    'total_withdrawals' => $totalWithdrawalsWithFilter,
                    'last_transaction_date' => $lastTransaction ? $lastTransaction->transaction_date->format('Y-m-d') : null,
                    'last_transaction_type' => $lastTransaction ? $lastTransaction->transaction_type : null,
                ];
            }
        }

        return $students;
    }

    private function formatSppTransactions($sppPayments, ?string $filterYear = null, ?string $filterMonth = null, ?string $startDate = null, ?string $endDate = null): array
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
                    'description' => "Pembayaran SPP Bulan " . DateHelper::getMonthName($detail->month) . " {$detail->year}",
                    'amount' => (float) $detail->amount,
                    'transaction_date' => $payment->payment_date->format('Y-m-d'),
                    'created_by' => $payment->creator->full_name ?? 'System',
                    'details' => [
                        'month' => $detail->month,
                        'year' => $detail->year,
                        'month_name' => DateHelper::getMonthName($detail->month),
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

    private function formatSavingsTransactions($savingsTransactions, ?string $filterYear = null, ?string $filterMonth = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $transactions = [];

        foreach ($savingsTransactions as $transaction) {
            $transactionDate = Carbon::parse($transaction->transaction_date);

            // Filter berdasarkan tahun jika ada
            if ($filterYear && $transactionDate->year != (int)$filterYear) {
                continue;
            }

            // Filter berdasarkan bulan jika ada
            if ($filterMonth && $transactionDate->month != (int)$filterMonth) {
                continue;
            }

            // Filter berdasarkan rentang tanggal jika ada
            if ($startDate && $endDate) {
                $filterStart = Carbon::parse($startDate);
                $filterEnd = Carbon::parse($endDate);

                if ($transactionDate->lt($filterStart) || $transactionDate->gt($filterEnd)) {
                    continue;
                }
            }

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

        // Hitung total SPP dan bulan yang sudah dibayar dari transaksi
        foreach ($transactions as $transaction) {
            if ($transaction['type'] === 'spp_payment') {
                $totalSpp += $transaction['amount'];
                $totalPaidMonths++;
            }
        }

        // Hitung total bulan yang belum dibayar dari data siswa
        foreach ($students as $student) {
            $totalUnpaidMonths += count($student['unpaid_months'] ?? []);
        }

        // Hitung total bulan akademik yang relevan (berdasarkan unpaid months)
        $totalRelevantMonths = $totalPaidMonths + $totalUnpaidMonths;

        $averagePayment = $totalPaidMonths > 0 ? $totalSpp / $totalPaidMonths : 0;
        $paymentPercentage = $totalRelevantMonths > 0 ?
            round(($totalPaidMonths / $totalRelevantMonths) * 100, 2) : 0;

        return [
            'total_students' => count($students),
            'total_spp_paid' => $totalSpp,
            'total_paid_months' => $totalPaidMonths,
            'total_unpaid_months' => $totalUnpaidMonths,
            'total_relevant_months' => $totalRelevantMonths,
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

    // ================== METHOD HELPER YANG TERSISA ==================

    private function getStudentsWithFinanceInfo(
        array $studentIds,
        ?string $year = null,
        ?string $month = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $students = [];

        foreach ($studentIds as $studentId) {
            $student = $this->parentFinanceRepository->getStudentById($studentId);
            if ($student) {
                $academicYear = $this->getStudentAcademicYear($student);

                $unpaidMonths = [];
                $unpaidMonthsDetail = [];

                if ($academicYear) {
                    $unpaidMonthsDetail = FinanceHelper::calculateUnpaidMonthsWithFilters(
                        $studentId,
                        $academicYear,
                        $year,
                        $month,
                        $startDate,
                        $endDate
                    );

                    $unpaidMonths = array_column($unpaidMonthsDetail, 'month');
                }

                $students[] = [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'class' => $student->class->name ?? 'Tidak ada kelas',
                    'grade_level' => $student->class->grade_level ?? null,
                    'savings_balance' => $this->parentFinanceRepository->getStudentCurrentSavingsBalance($studentId),
                    'unpaid_months' => $unpaidMonths,
                    'unpaid_months_detail' => $unpaidMonthsDetail,
                ];
            }
        }

        return $students;
    }

    private function formatCombinedTransactions($sppPayments, $savingsTransactions, ?string $filterYear = null, ?string $filterMonth = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $transactions = [];

        // Format SPP payments
        foreach ($sppPayments as $payment) {
            foreach ($payment->paymentDetails as $detail) {
                // Filter berdasarkan tahun jika ada
                if ($filterYear && $detail->year != (int)$filterYear) {
                    continue;
                }

                // Filter berdasarkan bulan jika ada
                if ($filterMonth && $detail->month != (int)$filterMonth) {
                    continue;
                }

                // Filter berdasarkan rentang tanggal jika ada
                if ($startDate && $endDate) {
                    $paymentDate = Carbon::parse($payment->payment_date);
                    $filterStart = Carbon::parse($startDate);
                    $filterEnd = Carbon::parse($endDate);

                    if ($paymentDate->lt($filterStart) || $paymentDate->gt($filterEnd)) {
                        continue;
                    }
                }

                $transactions[] = [
                    'id' => $detail->id,
                    'payment_id' => $payment->id,
                    'type' => 'spp_payment',
                    'student_id' => $payment->student_id,
                    'student_name' => $payment->student->full_name,
                    'class' => $payment->student->class->name ?? 'Tidak ada kelas',
                    'receipt_number' => $payment->receipt_number,
                    'description' => "Pembayaran SPP Bulan " . DateHelper::getMonthName($detail->month) . " {$detail->year}",
                    'amount' => (float) $detail->amount,
                    'transaction_date' => $payment->payment_date->format('Y-m-d'),
                    'created_by' => $payment->creator->full_name ?? 'System',
                    'details' => [
                        'month' => $detail->month,
                        'year' => $detail->year,
                        'month_name' => DateHelper::getMonthName($detail->month),
                        'payment_method' => $payment->payment_method,
                        'notes' => $payment->notes
                    ]
                ];
            }
        }

        // Format savings transactions
        foreach ($savingsTransactions as $transaction) {
            $transactionDate = Carbon::parse($transaction->transaction_date);

            // Filter berdasarkan tahun jika ada
            if ($filterYear && $transactionDate->year != (int)$filterYear) {
                continue;
            }

            // Filter berdasarkan bulan jika ada
            if ($filterMonth && $transactionDate->month != (int)$filterMonth) {
                continue;
            }

            // Filter berdasarkan rentang tanggal jika ada
            if ($startDate && $endDate) {
                $filterStart = Carbon::parse($startDate);
                $filterEnd = Carbon::parse($endDate);

                if ($transactionDate->lt($filterStart) || $transactionDate->gt($filterEnd)) {
                    continue;
                }
            }

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

    /**
     * Helper method untuk mendapatkan transaksi gabungan dengan filter
     */
    private function getCombinedTransactionsWithFilters(
        array $studentIds,
        ?string $year = null,
        ?string $month = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $sppPayments = $this->parentFinanceRepository->getSppPaymentsByStudentIdsWithFilters(
            $studentIds,
            $year,
            $month,
            $startDate,
            $endDate
        );

        $savingsTransactions = $this->parentFinanceRepository->getSavingsTransactionsByStudentIdsWithFilters(
            $studentIds,
            $year,
            $month,
            $startDate,
            $endDate
        );

        return $this->formatCombinedTransactions($sppPayments, $savingsTransactions, $year, $month, $startDate, $endDate);
    }
}
