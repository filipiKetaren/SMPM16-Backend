<?php

namespace App\Services\Finance;

use App\DTOs\PaymentData;
use App\DTOs\UpdatePaymentData;
use App\Helpers\DateHelper;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use App\Repositories\Interfaces\SppPaymentRepositoryInterface;
use App\Repositories\Interfaces\SppSettingRepositoryInterface;
use App\Services\BaseService;
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
            return $this->error('Gagal mengambil data siswa: ' . $e->getMessage(), null, 500);
        }
    }

    public function getStudentBills(int $studentId, ?int $year = null)
    {
        try {
            $student = $this->studentRepository->findStudentWithClassAndPayments($studentId);

            if (!$student) {
                return $this->error('Siswa tidak ditemukan', null, 404);
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
            return $this->error('Gagal memproses pembayaran: ' . $e->getMessage(), null, 500);
        }
    }

    public function getStudentPaymentHistory(int $studentId, ?int $year = null)
    {
        try {
            $student = $this->studentRepository->findStudentWithPaymentHistory($studentId);

            if (!$student) {
                return $this->error('Siswa tidak ditemukan', null, 404);
            }

            $currentYear = $year ?? Carbon::now()->year;
            $paymentHistory = $this->formatPaymentHistory($student, $currentYear);

            return $this->success($paymentHistory, 'Riwayat pembayaran siswa berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->error('Gagal mengambil riwayat pembayaran: ' . $e->getMessage(), null, 500);
        }
    }

    private function calculateStudentBills($student, ?int $year = null)
    {
        $currentYear = $year ?? Carbon::now()->year;
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
        $alreadyPaidMonths = $this->sppPaymentRepository->getStudentPaidMonths(
            $paymentData->studentId,
            $paymentYear
        );

        // Validasi duplikasi pembayaran
        $conflicts = $this->checkPaymentConflicts($paymentData, $alreadyPaidMonths, $paymentYear);
        if (!empty($conflicts)) {
            return $this->formatValidationError($conflicts, 'Bulan berikut sudah dibayar: ');
        }

        // Validasi urutan pembayaran
        $sequenceError = $this->validatePaymentSequence($paymentData, $alreadyPaidMonths, $paymentYear);
        if ($sequenceError) {
            return $sequenceError;
        }

        // Validasi calculation
        $calculationError = $this->validatePaymentCalculations($paymentData);
        if ($calculationError) {
            return $calculationError;
        }

        return $this->success(null, 'Validasi berhasil', 200);
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
            // Cek apakah payment exists
            $existingPayment = $this->sppPaymentRepository->findPayment($paymentId);
            if (!$existingPayment) {
                return $this->error('Pembayaran tidak ditemukan', null, 404);
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
            return $this->error('Gagal mengupdate pembayaran: ' . $e->getMessage(), null, 500);
        }
    }

    public function deletePayment(int $paymentId)
    {
        DB::beginTransaction();
        try {
            // Cek apakah payment exists
            $payment = $this->sppPaymentRepository->findPayment($paymentId);
            if (!$payment) {
                return $this->error('Pembayaran tidak ditemukan', null, 404);
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
            return $this->error('Gagal menghapus pembayaran: ' . $e->getMessage(), null, 500);
        }
    }

    public function getPaymentDetail(int $paymentId)
    {
        try {
            $payment = $this->sppPaymentRepository->getPaymentWithDetails($paymentId);

            if (!$payment) {
                return $this->error('Pembayaran tidak ditemukan', null, 404);
            }

            return $this->success($payment, 'Detail pembayaran berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->error('Gagal mengambil detail pembayaran: ' . $e->getMessage(), null, 500);
        }
    }

    private function validateUpdatePayment($existingPayment, UpdatePaymentData $updateData)
    {
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

        // Validasi calculation
        $calculationError = $this->validatePaymentCalculationsForUpdate($updateData);
        if ($calculationError) {
            return $calculationError;
        }

        return $this->success(null, 'Validasi update berhasil', 200);
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
                null,
                422
            );
        }

        $calculatedTotal = $updateData->subtotal - $updateData->discount + $updateData->lateFee;
        if ($calculatedTotal != $updateData->totalAmount) {
            return $this->error(
                "Total amount tidak sesuai perhitungan. Total: {$updateData->totalAmount}, Seharusnya: {$calculatedTotal}",
                null,
                422
            );
        }

        return null;
    }
}
