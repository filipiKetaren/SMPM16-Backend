<?php

namespace App\Services\Finance;

use App\DTOs\PaymentData;
use App\DTOs\UpdatePaymentData;
use App\Helpers\DateHelper;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use App\Repositories\Interfaces\SppPaymentRepositoryInterface;
use App\Repositories\Interfaces\SppSettingRepositoryInterface;
use App\Services\BaseService;
use App\Models\SppPayment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SppService extends BaseService
{
    public function __construct(
        private StudentRepositoryInterface $studentRepository,
        private SppPaymentRepositoryInterface $sppPaymentRepository,
        private SppSettingRepositoryInterface $sppSettingRepository
    ) {}

    public function getStudentsWithBills(array $filters = [])
    {
        try {
            $students = $this->studentRepository
                ->getActiveStudentsWithClassAndPayments($filters)
                ->map(fn($student) => $this->calculateStudentBills($student));

            return $this->success($students, 'Data siswa dengan tagihan berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil data siswa', $e);
        }
    }

    public function getStudentBills(int $studentId, ?int $year = null)
    {
        try {
            $student = $this->studentRepository->findStudentWithClassAndPayments($studentId);

            if (!$student) {
                return $this->notFoundError('Siswa tidak ditemukan', null, 404);
            }

            $currentYear = $year ?? Carbon::now()->year;
            $bills = $this->calculateStudentBills($student, $currentYear);

            return $this->success($bills, 'Detail tagihan siswa berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->error('Gagal mengambil detail tagihan: ' . $e->getMessage(), null, 500);
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

            $validationResult = $this->validatePayment($paymentData);
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

    public function getStudentPaymentHistory(int $studentId, ?int $year = null)
    {
        try {
            $student = $this->studentRepository->findStudentWithPaymentHistory($studentId);

            if (!$student) {
                return $this->notFoundError('Siswa tidak ditemukan');
            }

            $currentYear = $year ?? Carbon::now()->year;
            $paymentHistory = $this->formatPaymentHistory($student, $currentYear);

            return $this->success($paymentHistory, 'Riwayat pembayaran siswa berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil riwayat pembayaran: ', $e);
        }
    }

    private function calculateStudentBills($student, ?int $year = null)
    {
        $currentYear = $year ?? Carbon::now()->year;

        // Gunakan SppSettingRepository untuk mendapatkan setting berdasarkan grade level
        $sppSetting = $this->sppSettingRepository->getSettingByGradeLevel($student->class->grade_level);

        $monthlyAmount = $sppSetting ? $sppSetting->monthly_amount : 0;
        $paymentStatus = $this->calculatePaymentStatus($student, $currentYear);

        return [
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'full_name' => $student->full_name,
                'class' => $student->class->name,
                'grade_level' => $student->class->grade_level,
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
                if ($detail->year == $year) {
                    $paidMonths[] = $detail->month;
                    $paidMonthsDetails[] = [
                        'payment_id' => $payment->id, // ID dari tabel spp_payments
                        'payment_detail_id' => $detail->id, // ID dari tabel spp_payment_details
                        'month' => $detail->month,
                        'year' => $detail->year,
                        'amount' => (float) $detail->amount,
                        'payment_date' => $payment->payment_date->format('Y-m-d'),
                        'receipt_number' => $payment->receipt_number
                    ];
                }
            }
        }

        usort($paidMonthsDetails, fn($a, $b) => $a['month'] - $b['month']);

        $allMonths = range(1, 12);
        $unpaidMonths = array_values(array_diff($allMonths, $paidMonths));

        return [
            'paid_months' => $paidMonths,
            'paid_months_details' => $paidMonthsDetails,
            'unpaid_months' => $unpaidMonths
        ];
    }

    private function validatePayment(PaymentData $paymentData)
    {
        $paymentYear = $paymentData->paymentDetails[0]['year'] ?? Carbon::now()->year;

        // Validasi 1: Cek apakah siswa exists dan dapatkan data kelas
        $student = $this->studentRepository->findStudentWithClass($paymentData->studentId);
        if (!$student) {
            return $this->notFoundError('Siswa tidak ditemukan');
        }

        // Validasi 2: Dapatkan setting SPP berdasarkan grade level
        $sppSetting = $this->sppSettingRepository->getSettingByGradeLevel($student->class->grade_level);
        if (!$sppSetting) {
            return $this->error(
                'Setting SPP tidak ditemukan untuk tingkat kelas ' . $student->class->grade_level,
                ['grade_level' => $student->class->grade_level],
                422
            );
        }

        $monthlyAmount = $sppSetting->monthly_amount;

        // Validasi 3: Cek duplikasi pembayaran
        $alreadyPaidMonths = $this->sppPaymentRepository->getStudentPaidMonths(
            $paymentData->studentId,
            $paymentYear
        );

        $conflicts = $this->checkPaymentConflicts($paymentData, $alreadyPaidMonths, $paymentYear);
        if (!empty($conflicts)) {
            return $this->formatValidationError($conflicts, 'Bulan berikut sudah dibayar: ');
        }

        // Validasi 4: Cek urutan pembayaran
        $sequenceError = $this->validatePaymentSequence($paymentData, $alreadyPaidMonths, $paymentYear);
        if ($sequenceError) {
            return $sequenceError;
        }

        // ⚠️ VALIDASI BARU: Cek kesesuaian amount dengan setting SPP
        $amountValidation = $this->validatePaymentAmounts($paymentData, $monthlyAmount, $sppSetting);
        if ($amountValidation['status'] === 'error') {
            return $amountValidation;
        }

        // Validasi 5: Cek calculation
        $calculationError = $this->validatePaymentCalculations($paymentData);
        if ($calculationError) {
            return $calculationError;
        }

        return $this->success(null, 'Validasi berhasil', 200);
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

    private function checkPaymentConflicts(PaymentData $paymentData, array $alreadyPaidMonths, int $paymentYear): array
    {
        $conflicts = [];
        foreach ($paymentData->paymentDetails as $detail) {
            if ($detail['year'] == $paymentYear && in_array($detail['month'], $alreadyPaidMonths)) {
                $monthName = DateHelper::getMonthName($detail['month']);
                $conflicts[] = "{$monthName} {$detail['year']}";
            }
        }
        return $conflicts;
    }

    private function validatePaymentSequence(PaymentData $paymentData, array $alreadyPaidMonths, int $paymentYear)
    {
        $monthsToPay = array_column($paymentData->paymentDetails, 'month');
        sort($monthsToPay);

        $allPaidMonths = array_merge($alreadyPaidMonths, $monthsToPay);
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

    private function formatPaymentHistory($student, int $year)
    {
        return $student->sppPayments
            ->filter(fn($payment) => $payment->payment_date->year == $year)
            ->map(fn($payment) => [
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
                })
            ])
            ->values();
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
}
