<?php

namespace App\Services\Finance;

use App\DTOs\PaymentData;
use App\DTOs\UpdatePaymentData;
use App\Helpers\DateHelper;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use App\Repositories\Interfaces\SppPaymentRepositoryInterface;
use App\Repositories\Interfaces\SppSettingRepositoryInterface;
use App\Repositories\Interfaces\AcademicYearRepositoryInterface;
use App\Services\BaseService;
use App\Models\SppPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SppService extends BaseService
{
    public function __construct(
        private StudentRepositoryInterface $studentRepository,
        private SppPaymentRepositoryInterface $sppPaymentRepository,
        private SppSettingRepositoryInterface $sppSettingRepository,
        private AcademicYearRepositoryInterface $academicYearRepository
    ) {}

    public function getStudentsWithBills(array $filters = [])
    {
        try {
            $students = $this->studentRepository
                ->getActiveStudentsWithClassAndPayments($filters)
                ->map(function($student) {
                    try {
                        return $this->calculateStudentBills($student);
                    } catch (\Exception $e) {
                        Log::error('Error calculating bills for student ' . $student->id . ': ' . $e->getMessage());
                        return [
                            'student' => [
                                'id' => $student->id,
                                'nis' => $student->nis,
                                'full_name' => $student->full_name,
                                'class' => $student->class->name ?? 'Tidak ada kelas',
                                'grade_level' => $student->class->grade_level ?? 0,
                            ],
                            'bills' => [
                                'year' => date('Y'),
                                'monthly_amount' => 0,
                                'unpaid_months' => [],
                                'paid_months' => [],
                                'total_unpaid' => 0,
                                'unpaid_details' => [],
                                'paid_details' => [],
                            ]
                        ];
                    }
                });

            return $this->success($students, 'Data siswa dengan tagihan berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil data siswa', $e);
        }
    }

    /**
     * Generate SPP bills with academic year logic
     */
    public function generateStudentBills(int $studentId)
    {
        try {
            $student = $this->studentRepository->findStudentWithClassAndPayments($studentId);

            if (!$student) {
                return $this->notFoundError('Siswa tidak ditemukan');
            }

            // Dapatkan tahun akademik
            $academicYear = $this->getAcademicYearForStudent($student);
            if (!$academicYear) {
                return $this->error('Tahun akademik tidak ditemukan', null, 404);
            }

            // Dapatkan setting SPP
            $sppSetting = $this->sppSettingRepository->getSettingByGradeLevel(
                $student->class->grade_level,
                $academicYear->id
            );

            if (!$sppSetting) {
                return $this->error('Setting SPP tidak ditemukan', null, 404);
            }

            // Dapatkan daftar bulan akademik
            $academicMonths = $academicYear->getAcademicMonths();

            // Dapatkan bulan yang sudah dibayar
            $paidMonths = $this->getStudentPaidAcademicMonths($studentId, $academicYear->id);

            // Generate tagihan
            $bills = [];
            $totalUnpaid = 0;

            foreach ($academicMonths as $academicMonth) {
                $isPaid = false;
                $paymentInfo = null;

                // Cek apakah bulan ini sudah dibayar
                foreach ($paidMonths as $paid) {
                    if ($paid['month'] == $academicMonth['month'] && $paid['year'] == $academicMonth['year']) {
                        $isPaid = true;
                        $paymentInfo = $paid;
                        break;
                    }
                }

                $bill = [
                    'academic_year_id' => $academicYear->id,
                    'academic_year_name' => $academicYear->name,
                    'month' => $academicMonth['month'],
                    'month_name' => $academicMonth['month_name'],
                    'year' => $academicMonth['year'],
                    'amount' => (float) $sppSetting->monthly_amount,
                    'due_date' => $this->calculateDueDate(
                        $academicMonth['year'],
                        $academicMonth['month'],
                        $sppSetting->due_date
                    ),
                    'is_paid' => $isPaid,
                    'is_overdue' => $this->checkIfOverdue(
                        $academicMonth['year'],
                        $academicMonth['month'],
                        $sppSetting->due_date
                    ),
                    'late_fee_amount' => $isPaid ? 0 : $this->calculateLateFee(
                        $academicMonth['year'],
                        $academicMonth['month'],
                        $sppSetting
                    ),
                    'payment_info' => $paymentInfo
                ];

                if (!$isPaid) {
                    $totalUnpaid += $bill['amount'] + $bill['late_fee_amount'];
                }

                $bills[] = $bill;
            }

            // Urutkan berdasarkan bulan akademik
            usort($bills, function($a, $b) {
                if ($a['year'] == $b['year']) {
                    return $a['month'] <=> $b['month'];
                }
                return $a['year'] <=> $b['year'];
            });

            return $this->success([
                'student' => [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'class' => $student->class->name,
                    'grade_level' => $student->class->grade_level,
                ],
                'academic_year' => [
                    'id' => $academicYear->id,
                    'name' => $academicYear->name,
                    'start_date' => $academicYear->start_date,
                    'end_date' => $academicYear->end_date,
                    'start_month' => $academicYear->start_month,
                    'end_month' => $academicYear->end_month,
                ],
                'spp_setting' => [
                    'monthly_amount' => (float) $sppSetting->monthly_amount,
                    'due_date' => $sppSetting->due_date,
                    'late_fee_enabled' => $sppSetting->late_fee_enabled,
                    'late_fee_type' => $sppSetting->late_fee_type,
                    'late_fee_amount' => (float) $sppSetting->late_fee_amount,
                ],
                'bills' => $bills,
                'summary' => [
                    'total_months' => count($bills),
                    'paid_months' => count(array_filter($bills, fn($b) => $b['is_paid'])),
                    'unpaid_months' => count(array_filter($bills, fn($b) => !$b['is_paid'])),
                    'total_unpaid' => $totalUnpaid,
                    'total_paid' => array_sum(array_column(
                        array_filter($bills, fn($b) => $b['is_paid']),
                        'amount'
                    )),
                ]
            ], 'Tagihan SPP berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil tagihan SPP', $e);
        }
    }

    public function processPayment(array $data, int $createdBy)
    {
        DB::beginTransaction();
        try {
            $validationResult = $this->validatePaymentRequiredFields($data);
            if ($validationResult) {
                return $validationResult;
            }

            $paymentData = PaymentData::fromRequest($data);

            // Validasi berdasarkan tahun akademik
            $validationResult = $this->validatePaymentWithAcademicYear($paymentData);
            if ($validationResult['status'] === 'error') {
                return $validationResult;
            }

            $receiptNumber = $this->generateReceiptNumber();
            $payment = $this->createPayment($paymentData, $receiptNumber, $createdBy);

            DB::commit();

            $payment->load(['student', 'creator', 'paymentDetails']);

            return $this->success($payment, 'Pembayaran SPP berhasil diproses', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal memproses pembayaran', $e);
        }
    }

    /**
     * Validasi pembayaran dengan logika tahun akademik
     */
    private function validatePaymentWithAcademicYear(PaymentData $paymentData)
    {
        // 1. Validasi siswa
        $student = $this->studentRepository->findStudentWithClass($paymentData->studentId);
        if (!$student) {
            return $this->notFoundError('Siswa tidak ditemukan');
        }

        // 2. Dapatkan tahun akademik
        $academicYear = $this->getAcademicYearForStudent($student);
        if (!$academicYear) {
            return $this->error('Tahun akademik tidak ditemukan untuk siswa ini', null, 404);
        }

        // 3. Dapatkan setting SPP
        $sppSetting = $this->sppSettingRepository->getSettingByGradeLevel(
            $student->class->grade_level,
            $academicYear->id
        );

        if (!$sppSetting) {
            return $this->error(
                'Setting SPP tidak ditemukan untuk tingkat kelas ' . $student->class->grade_level,
                ['grade_level' => $student->class->grade_level],
                422
            );
        }

        $monthlyAmount = $sppSetting->monthly_amount;

        // 4. Validasi bulan dalam tahun akademik
        foreach ($paymentData->paymentDetails as $detail) {
            if (!$academicYear->isWithinAcademicYear($detail['month'], $detail['year'])) {
                $monthName = DateHelper::getMonthName($detail['month']);
                return $this->error(
                    "Bulan {$monthName} {$detail['year']} tidak termasuk dalam tahun akademik {$academicYear->name}",
                    null,
                    422
                );
            }
        }

        // 5. Validasi duplikasi pembayaran
        $alreadyPaidMonths = $this->sppPaymentRepository->getStudentPaidAcademicMonthsWithYear(
            $paymentData->studentId,
            $academicYear->id
        );

        $conflicts = $this->checkPaymentConflicts($paymentData, $alreadyPaidMonths);
        if (!empty($conflicts)) {
            return $this->formatValidationError($conflicts, 'Bulan berikut sudah dibayar: ');
        }

        // 6. Validasi urutan berdasarkan tahun akademik
        $sequenceError = $this->validateAcademicYearSequence($paymentData, $alreadyPaidMonths, $academicYear);
        if ($sequenceError) {
            return $sequenceError;
        }

        // 7. Validasi amount
        $amountValidation = $this->validatePaymentAmounts($paymentData, $monthlyAmount, $sppSetting);
        if ($amountValidation['status'] === 'error') {
            return $amountValidation;
        }

        // 8. Validasi calculation
        $calculationError = $this->validatePaymentCalculations($paymentData);
        if ($calculationError) {
            return $calculationError;
        }

        return $this->success(null, 'Validasi berhasil', 200);
    }

    /**
     * Check conflicts with academic year format
     */
    private function checkPaymentConflicts(PaymentData $paymentData, array $alreadyPaidMonths): array
    {
        $conflicts = [];
        foreach ($paymentData->paymentDetails as $detail) {
            foreach ($alreadyPaidMonths as $paid) {
                if ($detail['month'] == $paid['month'] && $detail['year'] == $paid['year']) {
                    $monthName = DateHelper::getMonthName($detail['month']);
                    $conflicts[] = "{$monthName} {$detail['year']}";
                }
            }
        }
        return $conflicts;
    }

    /**
     * Validasi urutan berdasarkan tahun akademik
     */
    private function validateAcademicYearSequence(PaymentData $paymentData, array $alreadyPaidMonths, $academicYear)
    {
        // Dapatkan daftar bulan akademik
        $academicMonths = $academicYear->getAcademicMonths();

        // Buat map urutan akademik
        $academicMonthOrder = [];
        foreach ($academicMonths as $index => $am) {
            $key = "{$am['year']}-{$am['month']}";
            $academicMonthOrder[$key] = $index + 1;
        }

        // Urutkan bulan yang akan dibayar berdasarkan urutan akademik
        $monthsToPay = $paymentData->paymentDetails;
        usort($monthsToPay, function($a, $b) use ($academicMonthOrder) {
            $keyA = "{$a['year']}-{$a['month']}";
            $keyB = "{$b['year']}-{$b['month']}";
            return ($academicMonthOrder[$keyA] ?? 0) <=> ($academicMonthOrder[$keyB] ?? 0);
        });

        // Gabungkan dengan yang sudah dibayar
        $allMonths = array_merge(
            array_map(fn($m) => ['year' => $m['year'], 'month' => $m['month']], $alreadyPaidMonths),
            array_map(fn($m) => ['year' => $m['year'], 'month' => $m['month']], $monthsToPay)
        );

        // Urutkan semua bulan berdasarkan urutan akademik
        usort($allMonths, function($a, $b) use ($academicMonthOrder) {
            $keyA = "{$a['year']}-{$a['month']}";
            $keyB = "{$b['year']}-{$b['month']}";
            return ($academicMonthOrder[$keyA] ?? 0) <=> ($academicMonthOrder[$keyB] ?? 0);
        });

        // Hapus duplikat
        $uniqueMonths = array_unique($allMonths, SORT_REGULAR);

        // Validasi tidak ada celah
        for ($i = 0; $i < count($uniqueMonths) - 1; $i++) {
            $currentKey = "{$uniqueMonths[$i]['year']}-{$uniqueMonths[$i]['month']}";
            $nextKey = "{$uniqueMonths[$i + 1]['year']}-{$uniqueMonths[$i + 1]['month']}";

            $currentOrder = $academicMonthOrder[$currentKey] ?? 0;
            $nextOrder = $academicMonthOrder[$nextKey] ?? 0;

            if ($nextOrder - $currentOrder > 1) {
                // Ada celah
                $missingOrders = range($currentOrder + 1, $nextOrder - 1);
                $missingMonths = [];

                foreach ($missingOrders as $order) {
                    foreach ($academicMonths as $am) {
                        $key = "{$am['year']}-{$am['month']}";
                        if (($academicMonthOrder[$key] ?? 0) == $order) {
                            $missingMonths[] = [
                                'month' => $am['month'],
                                'year' => $am['year'],
                                'month_name' => $am['month_name']
                            ];
                            break;
                        }
                    }
                }

                $missingList = array_map(
                    fn($m) => "{$m['month_name']} {$m['year']}",
                    $missingMonths
                );

                return $this->error(
                    "Harap bayar bulan " . implode(', ', $missingList) . " terlebih dahulu",
                    ['missing_months' => $missingMonths],
                    422
                );
            }
        }

        return null;
    }

    public function getStudentPaymentHistory(int $studentId, ?int $year = null)
    {
        try {
            $student = $this->studentRepository->findStudentWithPaymentHistory($studentId);

            if (!$student) {
                return $this->notFoundError('Siswa tidak ditemukan');
            }

            // Jika tahun tidak disediakan, tampilkan semua riwayat
            if ($year) {
                $paymentHistory = $this->formatPaymentHistory($student, $year);
            } else {
                $paymentHistory = $this->formatAllPaymentHistory($student);
            }

            return $this->success($paymentHistory, 'Riwayat pembayaran siswa berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil riwayat pembayaran: ', $e);
        }
    }

    /**
     * Format semua riwayat pembayaran tanpa filter tahun
     */
    private function formatAllPaymentHistory($student)
    {
        return $student->sppPayments
            ->sortByDesc('payment_date')
            ->map(fn($payment) => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'total_amount' => (float) $payment->total_amount,
                'payment_method' => $payment->payment_method,
                'created_by' => $payment->creator->full_name,
                'months_paid' => $payment->paymentDetails->map(function($detail) {
                    return [
                        'month' => $detail->month,
                        'year' => $detail->year,
                        'amount' => (float) $detail->amount,
                        'month_name' => DateHelper::getMonthName($detail->month)
                    ];
                }),
                'year' => $payment->payment_date->year // Tambahkan tahun untuk grouping di frontend
            ])
            ->values();
    }

    private function calculateStudentBills($student, ?int $year = null)
    {
        try {
            // Dapatkan tahun akademik untuk siswa
            $academicYear = $this->getAcademicYearForStudent($student);
            if (!$academicYear) {
                return $this->getFallbackStudentBills($student, $year);
            }

            // Dapatkan setting SPP
            $sppSetting = $this->sppSettingRepository->getSettingByGradeLevel(
                $student->class->grade_level,
                $academicYear->id
            );

            if (!$sppSetting) {
                return $this->getFallbackStudentBills($student, $year);
            }

            // Dapatkan daftar bulan akademik
            $academicMonths = $academicYear->getAcademicMonths();

            // Dapatkan bulan yang sudah dibayar
            $paidMonthsDetails = $this->getStudentPaidAcademicMonths($student->id, $academicYear->id);

            // Hitung bulan yang sudah dibayar
            $paidMonths = [];
            $paidMonthsFormatted = [];

            foreach ($paidMonthsDetails as $paid) {
                $paidMonths[] = $paid['month'];
                $paidMonthsFormatted[] = [
                    'payment_id' => $paid['payment_id'],
                    'payment_detail_id' => $paid['detail_id'],
                    'month' => $paid['month'],
                    'year' => $paid['year'],
                    'amount' => $paid['amount'],
                    'payment_date' => $paid['payment_date'],
                    'receipt_number' => $paid['receipt_number']
                ];
            }

            // Hitung bulan yang belum dibayar
            $unpaidMonths = [];
            foreach ($academicMonths as $academicMonth) {
                if (!in_array($academicMonth['month'], $paidMonths)) {
                    $unpaidMonths[] = $academicMonth['month'];
                }
            }

            return [
                'student' => [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'class' => $student->class->name ?? 'Tidak ada kelas',
                    'grade_level' => $student->class->grade_level ?? 0,
                ],
                'bills' => [
                    'year' => $academicYear->start_date->year,
                    'academic_year_name' => $academicYear->name,
                    'monthly_amount' => (float) $sppSetting->monthly_amount,
                    'unpaid_months' => $unpaidMonths,
                    'paid_months' => $paidMonths,
                    'total_unpaid' => (float) ($sppSetting->monthly_amount * count($unpaidMonths)),
                    'unpaid_details' => $unpaidMonths,
                    'paid_details' => $paidMonthsFormatted,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error in calculateStudentBills: ' . $e->getMessage());
            return $this->getFallbackStudentBills($student, $year);
        }
    }

    /**
 * Fallback method jika ada error
 */
    private function getFallbackStudentBills($student, ?int $year = null)
    {
        $currentYear = $year ?? Carbon::now()->year;

        // Gunakan SppSettingRepository untuk mendapatkan setting berdasarkan grade level
        $sppSetting = $this->sppSettingRepository->getSettingByGradeLevel($student->class->grade_level ?? 0);

        $monthlyAmount = $sppSetting ? $sppSetting->monthly_amount : 0;

        // Gunakan method lama untuk kompatibilitas
        $paymentStatus = $this->calculatePaymentStatus($student, $currentYear);

        return [
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'full_name' => $student->full_name,
                'class' => $student->class->name ?? 'Tidak ada kelas',
                'grade_level' => $student->class->grade_level ?? 0,
            ],
            'bills' => [
                'year' => $currentYear,
                'monthly_amount' => (float) $monthlyAmount,
                'unpaid_months' => $paymentStatus['unpaid_months'],
                'paid_months' => $paymentStatus['paid_months'],
                'total_unpaid' => (float) ($monthlyAmount * count($paymentStatus['unpaid_months'])),
                'unpaid_details' => $paymentStatus['unpaid_months'],
                'paid_details' => $paymentStatus['paid_months_details'],
            ]
        ];
    }

    private function calculatePaymentStatus($student, int $year)
    {
        $paidMonthsDetails = [];
        $paidMonths = [];

        foreach ($student->sppPayments as $payment) {
            foreach ($payment->paymentDetails as $detail) {
                // Catat semua pembayaran, tidak filter berdasarkan tahun
                $paidMonths[] = $detail->month;
                $paidMonthsDetails[] = [
                    'payment_id' => $payment->id,
                    'payment_detail_id' => $detail->id,
                    'month' => $detail->month,
                    'year' => $detail->year,
                    'amount' => (float) $detail->amount,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'receipt_number' => $payment->receipt_number
                ];
            }
        }

        usort($paidMonthsDetails, fn($a, $b) => $a['month'] - $b['month']);

        // Karena kita menggunakan tahun akademik, tidak menggunakan range(1,12)
        // Tapi kita perlu mengetahui bulan akademik yang tersedia
        $academicYear = $this->getAcademicYearForStudent($student);

        if ($academicYear) {
            $academicMonths = $academicYear->getAcademicMonths();
            $allAcademicMonths = array_map(fn($am) => $am['month'], $academicMonths);
            $unpaidMonths = array_values(array_diff($allAcademicMonths, $paidMonths));
        } else {
            // Fallback ke logika lama
            $allMonths = range(1, 12);
            $unpaidMonths = array_values(array_diff($allMonths, $paidMonths));
        }

        return [
            'paid_months' => $paidMonths,
            'paid_months_details' => $paidMonthsDetails,
            'unpaid_months' => $unpaidMonths
        ];
    }

    private function validatePaymentAmounts(PaymentData $paymentData, float $monthlyAmount, $sppSetting)
    {
        $errors = [];

        // Validasi amount per bulan
        foreach ($paymentData->paymentDetails as $detail) {
            if ($detail['amount'] != $monthlyAmount) {
                $monthName = DateHelper::getMonthName($detail['month']);
                $errors[] = "Amount untuk bulan {$monthName} {$detail['year']} tidak sesuai. " .
                           "Seharusnya: Rp " . number_format($monthlyAmount, 0, ',', '.') .
                           ", Yang dimasukkan: Rp " . number_format($detail['amount'], 0, ',', '.');
            }
        }

        // Validasi subtotal
        $expectedSubtotal = count($paymentData->paymentDetails) * $monthlyAmount;
        if ($paymentData->subtotal != $expectedSubtotal) {
            $errors[] = "Subtotal tidak sesuai. " .
                       "Seharusnya: Rp " . number_format($expectedSubtotal, 0, ',', '.') .
                       " ({$expectedSubtotal}), " .
                       "Yang dimasukkan: Rp " . number_format($paymentData->subtotal, 0, ',', '.') .
                       " ({$paymentData->subtotal})";
        }

        // Validasi total amount (termasuk diskon dan denda)
        $expectedTotal = $expectedSubtotal - $paymentData->discount + $paymentData->lateFee;
        if ($paymentData->totalAmount != $expectedTotal) {
            $errors[] = "Total amount tidak sesuai. " .
                       "Seharusnya: Rp " . number_format($expectedTotal, 0, ',', '.') .
                       " ({$expectedTotal}), " .
                       "Yang dimasukkan: Rp " . number_format($paymentData->totalAmount, 0, ',', '.') .
                       " ({$paymentData->totalAmount})";
        }

        // Validasi denda keterlambatan jika applicable
        $lateFeeValidation = $this->validateLateFee($paymentData, $sppSetting);
        if ($lateFeeValidation) {
            $errors[] = $lateFeeValidation;
        }

        if (!empty($errors)) {
            return $this->error(
                'Validasi nominal pembayaran gagal',
                [
                    'errors' => $errors,
                    'monthly_amount_setting' => $monthlyAmount,
                    'grade_level' => $sppSetting->grade_level,
                    'expected_subtotal' => $expectedSubtotal,
                    'expected_total' => $expectedTotal
                ],
                422
            );
        }

        return $this->success(null, 'Validasi nominal berhasil', 200);
    }

    /**
     * Validasi denda keterlambatan
     */
    private function validateLateFee(PaymentData $paymentData, $sppSetting)
    {
        if ($paymentData->lateFee > 0 && !$sppSetting->late_fee_enabled) {
            return "Denda keterlambatan tidak diizinkan untuk tingkat kelas ini";
        }

        if ($sppSetting->late_fee_enabled && $paymentData->lateFee > 0) {
            $paymentDate = Carbon::parse($paymentData->paymentDate);
            $dueDate = Carbon::createFromDate(
                $paymentData->paymentDetails[0]['year'],
                $paymentData->paymentDetails[0]['month'],
                $sppSetting->due_date
            );

            // Jika pembayaran masih sebelum due date, tidak boleh ada denda
            if ($paymentDate->lte($dueDate) && $paymentData->lateFee > 0) {
                return "Tidak boleh ada denda karena pembayaran masih sebelum tanggal jatuh tempo";
            }

            // Validasi jumlah denda sesuai setting
            $expectedLateFee = $this->calculateExpectedLateFee($paymentData, $sppSetting);
            if ($paymentData->lateFee != $expectedLateFee) {
                return "Jumlah denda tidak sesuai. Seharusnya: Rp " .
                       number_format($expectedLateFee, 0, ',', '.') .
                       ", Yang dimasukkan: Rp " . number_format($paymentData->lateFee, 0, ',', '.');
            }
        }

        return null;
    }

    /**
     * Hitung denda yang seharusnya
     */
    private function calculateExpectedLateFee(PaymentData $paymentData, $sppSetting)
    {
        if (!$sppSetting->late_fee_enabled) {
            return 0;
        }

        $paymentDate = Carbon::parse($paymentData->paymentDate);
        $totalLateFee = 0;

        foreach ($paymentData->paymentDetails as $detail) {
            $dueDate = Carbon::createFromDate($detail['year'], $detail['month'], $sppSetting->due_date);

            // Jika telat bayar
            if ($paymentDate->gt($dueDate)) {
                if ($sppSetting->late_fee_type === 'percentage') {
                    $lateFee = $detail['amount'] * ($sppSetting->late_fee_amount / 100);
                } else {
                    $lateFee = $sppSetting->late_fee_amount;
                }
                $totalLateFee += $lateFee;
            }
        }

        return $totalLateFee;
    }

    private function validatePaymentCalculations(PaymentData $paymentData)
    {
        $calculatedSubtotal = array_sum(array_column($paymentData->paymentDetails, 'amount'));
        if ($calculatedSubtotal != $paymentData->subtotal) {
            return $this->error(
                "Subtotal tidak sesuai dengan jumlah detail pembayaran.",
                ['Subtotal' => $paymentData->subtotal,
                'Jumlah_detail' => $calculatedSubtotal],
                422
            );
        }

        $calculatedTotal = $paymentData->subtotal - $paymentData->discount + $paymentData->lateFee;
        if ($calculatedTotal != $paymentData->totalAmount) {
            return $this->error(
                "Total amount tidak sesuai perhitungan.",
                ['Total' => $paymentData->totalAmount,
                'Seharusnya' => $calculatedTotal],
                422
            );
        }

        return null;
    }

    private function formatValidationError(array $conflicts, string $message)
    {
        $conflictList = implode(', ', $conflicts);
        return $this->error(
            $message . $conflictList,
            ['conflicted_months' => $conflicts],
            422
        );
    }

    private function generateReceiptNumber(): string
    {
        $count = $this->sppPaymentRepository->getPaymentCount();
        return 'KWT/SPP/' . date('Y') . '/' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }

    private function createPayment(PaymentData $paymentData, string $receiptNumber, int $createdBy)
    {
        $payment = $this->sppPaymentRepository->createPayment([
            'receipt_number' => $receiptNumber,
            'student_id' => $paymentData->studentId,
            'payment_date' => $paymentData->paymentDate,
            'subtotal' => $paymentData->subtotal,
            'discount' => $paymentData->discount,
            'late_fee' => $paymentData->lateFee,
            'total_amount' => $paymentData->totalAmount,
            'payment_method' => $paymentData->paymentMethod,
            'notes' => $paymentData->notes,
            'created_by' => $createdBy,
        ]);

        foreach ($paymentData->paymentDetails as $detail) {
            $payment->paymentDetails()->create([
                'month' => $detail['month'],
                'year' => $detail['year'],
                'amount' => $detail['amount'],
            ]);
        }

        return $payment;
    }

    private function formatPaymentHistory($student, ?int $year = null)
    {
        if ($year) {
            // Filter berdasarkan tahun jika disediakan
            return $student->sppPayments
                ->filter(fn($payment) => $payment->payment_date->year == $year)
                ->sortByDesc('payment_date')
                ->map(fn($payment) => $this->formatPaymentHistoryItem($payment))
                ->values();
        } else {
            // Tampilkan semua jika tidak ada tahun
            return $student->sppPayments
                ->sortByDesc('payment_date')
                ->map(fn($payment) => $this->formatPaymentHistoryItem($payment))
                ->values();
        }
    }

    /**
     * Format item riwayat pembayaran
     */
    private function formatPaymentHistoryItem($payment)
    {
        return [
            'id' => $payment->id,
            'receipt_number' => $payment->receipt_number,
            'payment_date' => $payment->payment_date->format('Y-m-d'),
            'total_amount' => (float) $payment->total_amount,
            'payment_method' => $payment->payment_method,
            'created_by' => $payment->creator->full_name ?? 'Unknown',
            'months_paid' => $payment->paymentDetails->map(function($detail) {
                return [
                    'month' => $detail->month,
                    'year' => $detail->year,
                    'amount' => (float) $detail->amount,
                    'month_name' => DateHelper::getMonthName($detail->month)
                ];
            })->toArray()
        ];
    }

    public function updatePayment(int $paymentId, array $data, int $updatedBy)
    {
        DB::beginTransaction();
        try {
            // Validasi required fields untuk update payment
            $validationResult = $this->validatePaymentRequiredFields($data);
            if ($validationResult) {
                return $validationResult;
            }

            // Cek apakah payment exists
            $existingPayment = $this->sppPaymentRepository->findPayment($paymentId);
            if (!$existingPayment) {
                return $this->notFoundError('Pembayaran tidak ditemukan');
            }

            $updateData = UpdatePaymentData::fromRequest($data);

            // Validasi update payment
            $validationResult = $this->validateUpdatePayment($existingPayment, $updateData);
            if ($validationResult['status'] === 'error') {
                return $validationResult;
            }

            // Update payment
            $updated = $this->sppPaymentRepository->updatePayment($paymentId, [
                'payment_date' => $updateData->paymentDate,
                'subtotal' => $updateData->subtotal,
                'discount' => $updateData->discount,
                'late_fee' => $updateData->lateFee,
                'total_amount' => $updateData->totalAmount,
                'payment_method' => $updateData->paymentMethod,
                'notes' => $updateData->notes,
            ]);

            if (!$updated) {
                return $this->error('Gagal mengupdate pembayaran', null, 500);
            }

            // Hapus payment details lama dan buat yang baru
            $existingPayment->paymentDetails()->delete();

            foreach ($updateData->paymentDetails as $detail) {
                $existingPayment->paymentDetails()->create([
                    'month' => $detail['month'],
                    'year' => $detail['year'],
                    'amount' => $detail['amount'],
                ]);
            }

            DB::commit();

            // Reload data terbaru
            $payment = $this->sppPaymentRepository->getPaymentWithDetails($paymentId);

            return $this->success($payment, 'Pembayaran SPP berhasil diupdate', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal mengupdate pembayaran: ' , $e);
        }
    }

    public function deletePayment(int $paymentId)
    {
        DB::beginTransaction();
        try {
            // Cek apakah payment exists
            $payment = $this->sppPaymentRepository->findPayment($paymentId);
            if (!$payment) {
                return $this->notFoundError('Pembayaran tidak ditemukan');
            }

            // ✅ VALIDASI BARU: Hanya boleh menghapus pembayaran terakhir
            $validationResult = $this->validateDeletePayment($payment);
            if ($validationResult['status'] === 'error') {
                return $validationResult;
            }

            // Hapus payment details terlebih dahulu
            $payment->paymentDetails()->delete();

            // Hapus payment
            $deleted = $this->sppPaymentRepository->deletePayment($paymentId);

            if (!$deleted) {
                return $this->error('Gagal menghapus pembayaran', null, 500);
            }

            DB::commit();

            return $this->success(null, 'Pembayaran SPP berhasil dihapus', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal menghapus pembayaran: ', $e);
        }
    }

    /**
     * Validasi required fields untuk payment
     */
    private function validatePaymentRequiredFields(array $data)
    {
        $errors = [];
        $requiredFields = [
            'student_id' => 'Siswa',
            'payment_date' => 'Tanggal pembayaran',
            'subtotal' => 'Subtotal',
            'total_amount' => 'Total amount',
            'payment_method' => 'Metode pembayaran',
            'payment_details' => 'Detail pembayaran'
        ];

        foreach ($requiredFields as $field => $label) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = ["{$label} harus diisi"];
            }
        }

        // Validasi tipe data numerik
        if (isset($data['subtotal']) && !is_numeric($data['subtotal'])) {
            $errors['subtotal'] = ['Subtotal harus berupa angka'];
        }

        if (isset($data['total_amount']) && !is_numeric($data['total_amount'])) {
            $errors['total_amount'] = ['Total amount harus berupa angka'];
        }

        if (isset($data['discount']) && !is_numeric($data['discount'])) {
            $errors['discount'] = ['Diskon harus berupa angka'];
        }

        if (isset($data['late_fee']) && !is_numeric($data['late_fee'])) {
            $errors['late_fee'] = ['Denda harus berupa angka'];
        }

        // Validasi payment_details array
        if (isset($data['payment_details']) && !is_array($data['payment_details'])) {
            $errors['payment_details'] = ['Detail pembayaran harus berupa array'];
        }

        // Validasi setiap item dalam payment_details
        if (isset($data['payment_details']) && is_array($data['payment_details'])) {
            foreach ($data['payment_details'] as $index => $detail) {
                if (!isset($detail['month']) || $detail['month'] === '') {
                    $errors["payment_details.{$index}.month"] = ['Bulan harus diisi'];
                } elseif (!is_numeric($detail['month']) || $detail['month'] < 1 || $detail['month'] > 12) {
                    $errors["payment_details.{$index}.month"] = ['Bulan harus antara 1 sampai 12'];
                }

                if (!isset($detail['year']) || $detail['year'] === '') {
                    $errors["payment_details.{$index}.year"] = ['Tahun harus diisi'];
                } elseif (!is_numeric($detail['year'])) {
                    $errors["payment_details.{$index}.year"] = ['Tahun harus berupa angka'];
                }

                if (!isset($detail['amount']) || $detail['amount'] === '') {
                    $errors["payment_details.{$index}.amount"] = ['Amount harus diisi'];
                } elseif (!is_numeric($detail['amount']) || $detail['amount'] <= 0) {
                    $errors["payment_details.{$index}.amount"] = ['Amount harus berupa angka positif'];
                }
            }
        }

        if (!empty($errors)) {
            return $this->validationError($errors, 'Data pembayaran tidak lengkap');
        }

        return null;
    }

    /**
     * ✅ VALIDASI BARU: Validasi untuk penghapusan pembayaran
     */
    private function validateDeletePayment(SppPayment $payment)
    {
        // Dapatkan pembayaran terakhir untuk siswa ini
        $latestPayment = $this->sppPaymentRepository->getLatestPaymentByStudent($payment->student_id);

        // Jika tidak ada pembayaran terakhir atau ID tidak sama, berarti ini bukan pembayaran terakhir
        if (!$latestPayment || $latestPayment->id !== $payment->id) {
            return $this->error(
                'Hanya pembayaran terakhir yang dapat dihapus. Untuk pembayaran sebelumnya, gunakan fitur ubah.',
                [
                    'allowed_action' => 'update',
                    'current_payment_date' => $payment->payment_date->format('Y-m-d'),
                    'latest_payment_date' => $latestPayment ? $latestPayment->payment_date->format('Y-m-d') : null,
                    'payment_months' => $payment->paymentDetails->map(function($detail) {
                        return [
                            'month' => $detail->month,
                            'year' => $detail->year,
                            'month_name' => DateHelper::getMonthName($detail->month)
                        ];
                    })->toArray()
                ],
                422
            );
        }

        // ✅ Validasi tambahan: Cek apakah pembayaran ini sudah lewat waktu tertentu (opsional)
        $paymentAgeInDays = $payment->created_at->diffInDays(now());
        if ($paymentAgeInDays > 30) {
            return $this->error(
                'Pembayaran yang sudah lebih dari 30 hari tidak dapat dihapus. Silakan gunakan fitur ubah.',
                [
                    'payment_age_days' => $paymentAgeInDays,
                    'max_allowed_days' => 30,
                    'allowed_action' => 'update'
                ],
                422
            );
        }

        return $this->success(null, 'Validasi penghapusan berhasil', 200);
    }

    public function getPaymentDetail(int $paymentId)
    {
        try {
            $payment = $this->sppPaymentRepository->getPaymentWithDetails($paymentId);

            if (!$payment) {
                return $this->notFoundError('Pembayaran tidak ditemukan');
            }

            return $this->success($payment, 'Detail pembayaran berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil detail pembayaran: ' , $e);
        }
    }

    private function validateUpdatePayment($existingPayment, UpdatePaymentData $updateData)
    {
        $paymentYear = $updateData->paymentDetails[0]['year'] ?? Carbon::now()->year;

        // Ambil data siswa dan setting
        $student = $this->studentRepository->findStudentWithClass($existingPayment->student_id);
        if (!$student) {
            return $this->notFoundError('Data siswa tidak ditemukan');
        }

        $sppSetting = $this->sppSettingRepository->getSettingByGradeLevel($student->class->grade_level);
        if (!$sppSetting) {
            return $this->error(
                'Setting SPP tidak ditemukan untuk tingkat kelas ' . $student->class->grade_level,
                ['grade_level' => $student->class->grade_level],
                422
            );
        }

        $paymentYear = $updateData->paymentDetails[0]['year'] ?? Carbon::now()->year;

        // Ambil bulan yang sudah dibayar oleh siswa ini (kecuali pembayaran yang sedang diupdate)
        $existingPaidMonths = $this->sppPaymentRepository->getStudentPaidMonths(
            $existingPayment->student_id,
            $paymentYear
        );

        // Hapus bulan-bulan dari pembayaran ini dari daftar paid months
        $currentPaymentMonths = [];
        foreach ($existingPayment->paymentDetails as $detail) {
            if ($detail->year == $paymentYear) {
                $key = array_search($detail->month, $existingPaidMonths);
                if ($key !== false) {
                    unset($existingPaidMonths[$key]);
                }
            }
        }
        $existingPaidMonths = array_values($existingPaidMonths);

        // Validasi duplikasi pembayaran
        $conflicts = $this->checkPaymentConflictsForUpdate($updateData, $existingPaidMonths, $paymentYear);
        if (!empty($conflicts)) {
            return $this->formatValidationError($conflicts, 'Bulan berikut sudah dibayar di pembayaran lain: ');
        }

        // Validasi urutan pembayaran
        $sequenceError = $this->validatePaymentSequenceForUpdate($existingPayment->student_id, $updateData, $existingPaidMonths, $paymentYear);
        if ($sequenceError) {
            return $sequenceError;
        }

        $amountValidation = $this->validateUpdatePaymentAmounts($updateData, $sppSetting->monthly_amount, $sppSetting);
        if ($amountValidation['status'] === 'error') {
            return $amountValidation;
        }

        // Validasi calculation
        $calculationError = $this->validatePaymentCalculationsForUpdate($updateData);
        if ($calculationError) {
            return $calculationError;
        }

        return $this->success(null, 'Validasi update berhasil', 200);
    }

    /**
     * Validasi amount untuk update payment
     */
    private function validateUpdatePaymentAmounts(UpdatePaymentData $updateData, float $monthlyAmount, $sppSetting)
    {
        $errors = [];

        // Validasi amount per bulan
        foreach ($updateData->paymentDetails as $detail) {
            if ($detail['amount'] != $monthlyAmount) {
                $monthName = DateHelper::getMonthName($detail['month']);
                $errors[] = "Amount untuk bulan {$monthName} {$detail['year']} tidak sesuai. " .
                           "Seharusnya: Rp " . number_format($monthlyAmount, 0, ',', '.');
            }
        }

        // Validasi subtotal
        $expectedSubtotal = count($updateData->paymentDetails) * $monthlyAmount;
        if ($updateData->subtotal != $expectedSubtotal) {
            $errors[] = "Subtotal tidak sesuai. Seharusnya: Rp " . number_format($expectedSubtotal, 0, ',', '.');
        }

        // Validasi total amount
        $expectedTotal = $expectedSubtotal - $updateData->discount + $updateData->lateFee;
        if ($updateData->totalAmount != $expectedTotal) {
            $errors[] = "Total amount tidak sesuai. Seharusnya: Rp " . number_format($expectedTotal, 0, ',', '.');
        }

        if (!empty($errors)) {
            return $this->error(
                'Validasi nominal pembayaran gagal',
                ['errors' => $errors],
                422
            );
        }

        return $this->success(null, 'Validasi nominal berhasil', 200);
    }

    private function checkPaymentConflictsForUpdate(UpdatePaymentData $updateData, array $existingPaidMonths, int $paymentYear): array
    {
        $conflicts = [];
        foreach ($updateData->paymentDetails as $detail) {
            if ($detail['year'] == $paymentYear && in_array($detail['month'], $existingPaidMonths)) {
                $monthName = DateHelper::getMonthName($detail['month']);
                $conflicts[] = "{$monthName} {$detail['year']}";
            }
        }
        return $conflicts;
    }

    private function validatePaymentSequenceForUpdate(int $studentId, UpdatePaymentData $updateData, array $existingPaidMonths, int $paymentYear)
    {
        $monthsToPay = array_column($updateData->paymentDetails, 'month');
        sort($monthsToPay);

        $allPaidMonths = array_merge($existingPaidMonths, $monthsToPay);
        $allPaidMonths = array_unique($allPaidMonths);
        sort($allPaidMonths);

        if (!empty($allPaidMonths)) {
            $allPossibleMonths = range(1, max($allPaidMonths));
            $missingMonths = array_diff($allPossibleMonths, $allPaidMonths);

            if (!empty($missingMonths)) {
                $firstMissingMonth = min($missingMonths);
                $monthName = DateHelper::getMonthName($firstMissingMonth);
                return $this->error(
                    "Harap bayar bulan {$monthName} {$paymentYear} terlebih dahulu sebelum melanjutkan ke bulan berikutnya",
                    [
                        'missing_month' => $firstMissingMonth,
                        'missing_month_name' => $monthName,
                        'year' => $paymentYear
                    ],
                    422
                );
            }
        }

        return null;
    }

    private function validatePaymentCalculationsForUpdate(UpdatePaymentData $updateData)
    {
        $calculatedSubtotal = array_sum(array_column($updateData->paymentDetails, 'amount'));
        if ($calculatedSubtotal != $updateData->subtotal) {
            return $this->error(
                "Subtotal tidak sesuai dengan jumlah detail pembayaran. Subtotal: {$updateData->subtotal}, Jumlah detail: {$calculatedSubtotal}",
                ['Subtotal' => $updateData->subtotal,
                'Jumlah_detail' => $calculatedSubtotal],
                422
            );
        }

        $calculatedTotal = $updateData->subtotal - $updateData->discount + $updateData->lateFee;
        if ($calculatedTotal != $updateData->totalAmount) {
            return $this->error(
                "Total amount tidak sesuai perhitungan. Total: {$updateData->totalAmount}, Seharusnya: {$calculatedTotal}",
                ['Total' => $updateData->totalAmount,
                'Seharusnya' => $calculatedTotal],
                422
            );
        }

        return null;
    }

    /**
     * Get academic year for student
     */
    private function getAcademicYearForStudent($student)
    {
        // Coba dapatkan dari class siswa
        if ($student->class && $student->class->academicYear) {
            return $student->class->academicYear;
        }

        // Jika tidak ada, cari tahun akademik aktif
        return $this->academicYearRepository->getActiveAcademicYear();
    }

    /**
     * Get student paid academic months
     */
    private function getStudentPaidAcademicMonths(int $studentId, int $academicYearId): array
    {
        // Ambil semua pembayaran siswa
        $payments = $this->sppPaymentRepository->getPaymentsByStudentAndAcademicYear($studentId, $academicYearId);

        $paidMonths = [];

        foreach ($payments as $payment) {
            foreach ($payment->paymentDetails as $detail) {
                $paidMonths[] = [
                    'month' => $detail->month,
                    'year' => $detail->year,
                    'amount' => (float) $detail->amount,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'receipt_number' => $payment->receipt_number,
                    'payment_id' => $payment->id,
                    'detail_id' => $detail->id,
                ];
            }
        }

        return $paidMonths;
    }

    /**
     * Calculate due date
     */
    private function calculateDueDate(int $year, int $month, int $dueDay): string
    {
        return Carbon::create($year, $month, $dueDay)->format('Y-m-d');
    }

    /**
     * Check if bill is overdue
     */
    private function checkIfOverdue(int $year, int $month, int $dueDay): bool
    {
        $dueDate = Carbon::create($year, $month, $dueDay);
        return now()->gt($dueDate);
    }

    /**
     * Calculate late fee
     */
    private function calculateLateFee(int $year, int $month, $sppSetting): float
    {
        if (!$sppSetting->late_fee_enabled) {
            return 0;
        }

        $dueDate = Carbon::create($year, $month, $sppSetting->due_date);

        if (now()->lte($dueDate)) {
            return 0;
        }

        if ($sppSetting->late_fee_type === 'percentage') {
            return $sppSetting->monthly_amount * ($sppSetting->late_fee_amount / 100);
        } else {
            return $sppSetting->late_fee_amount;
        }
    }
}
