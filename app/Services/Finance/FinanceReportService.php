<?php
// app/Services/Finance/FinanceReportService.php

namespace App\Services\Finance;

use App\Repositories\Interfaces\FinanceReportRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SppReportExport;
use App\Exports\SavingsReportExport;
use App\Exports\FinancialSummaryExport;

class FinanceReportService extends BaseService
{
    public function __construct(
        private FinanceReportRepositoryInterface $reportRepository
    ) {}

    /**
     * Generate SPP Report
     */
    public function generateSppReport(array $filters, int $userId)
    {
        DB::beginTransaction();
        try {
            // Validasi filter
            $validationResult = $this->validateReportFilters($filters);
            if ($validationResult) {
                return $validationResult;
            }

            // Dapatkan data laporan
            $reportData = $this->reportRepository->getSppReportData($filters);

            // Generate file berdasarkan format
            $format = $filters['format'] ?? 'excel';
            $fileData = $this->generateFile($reportData, $format, 'spp');

            // Simpan log laporan
            $logData = $this->saveReportLog([
                'user_id' => $userId,
                'report_type' => 'spp',
                'report_format' => $format,
                'period_type' => $filters['period_type'] ?? 'monthly',
                'year' => $filters['year'] ?? date('Y'),
                'month' => $filters['month'] ?? null,
                'academic_year_id' => $filters['academic_year_id'] ?? null,
                'file_path' => $fileData['file_path'],
                'file_name' => $fileData['file_name']
            ]);

            DB::commit();

            return $this->success($fileData, 'Laporan SPP berhasil digenerate', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal generate laporan SPP', $e);
        }
    }

    /**
     * Generate Savings Report
     */
    public function generateSavingsReport(array $filters, int $userId)
    {
        DB::beginTransaction();
        try {
            // Validasi filter
            $validationResult = $this->validateReportFilters($filters);
            if ($validationResult) {
                return $validationResult;
            }

            // Dapatkan data laporan
            $reportData = $this->reportRepository->getSavingsReportData($filters);

            // Generate file berdasarkan format
            $format = $filters['format'] ?? 'excel';
            $fileData = $this->generateFile($reportData, $format, 'savings');

            // Simpan log laporan
            $logData = $this->saveReportLog([
                'user_id' => $userId,
                'report_type' => 'savings',
                'report_format' => $format,
                'period_type' => $filters['period_type'] ?? 'monthly',
                'year' => $filters['year'] ?? date('Y'),
                'month' => $filters['month'] ?? null,
                'academic_year_id' => $filters['academic_year_id'] ?? null,
                'file_path' => $fileData['file_path'],
                'file_name' => $fileData['file_name']
            ]);

            DB::commit();

            return $this->success($fileData, 'Laporan Tabungan berhasil digenerate', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal generate laporan Tabungan', $e);
        }
    }

    /**
     * Generate Financial Summary Report (SPP + Tabungan)
     */
    public function generateFinancialSummary(array $filters, int $userId)
    {
        DB::beginTransaction();
        try {
            // Validasi filter
            $validationResult = $this->validateReportFilters($filters);
            if ($validationResult) {
                return $validationResult;
            }

            // Dapatkan data laporan SPP dan Tabungan
            $sppData = $this->reportRepository->getSppReportData($filters);
            $savingsData = $this->reportRepository->getSavingsReportData($filters);

            // Gabungkan data untuk summary
            $summaryData = [
                'spp' => $sppData,
                'savings' => $savingsData,
                'total_finance' => [
                    'total_income' => ($sppData['total_income'] ?? 0) + ($savingsData['total_deposits'] ?? 0),
                    'total_expenditure' => ($savingsData['total_withdrawals'] ?? 0),
                    'net_income' => (($sppData['total_income'] ?? 0) + ($savingsData['total_deposits'] ?? 0)) - ($savingsData['total_withdrawals'] ?? 0)
                ]
            ];

            // Generate file berdasarkan format
            $format = $filters['format'] ?? 'excel';
            $fileData = $this->generateFile($summaryData, $format, 'financial_summary');

            // Simpan log laporan
            $logData = $this->saveReportLog([
                'user_id' => $userId,
                'report_type' => 'financial_summary',
                'report_format' => $format,
                'period_type' => $filters['period_type'] ?? 'monthly',
                'year' => $filters['year'] ?? date('Y'),
                'month' => $filters['month'] ?? null,
                'academic_year_id' => $filters['academic_year_id'] ?? null,
                'file_path' => $fileData['file_path'],
                'file_name' => $fileData['file_name']
            ]);

            DB::commit();

            return $this->success($fileData, 'Laporan Ringkasan Keuangan berhasil digenerate', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal generate laporan Ringkasan Keuangan', $e);
        }
    }

    /**
     * Validasi filter laporan
     */
    private function validateReportFilters(array $filters)
    {
        $errors = [];

        // Validasi period_type
        if (!isset($filters['period_type']) || !in_array($filters['period_type'], ['monthly', 'yearly'])) {
            $errors['period_type'] = ['Tipe periode harus monthly atau yearly'];
        }

        // Validasi tahun
        if (!isset($filters['year']) || !is_numeric($filters['year'])) {
            $errors['year'] = ['Tahun harus diisi dan berupa angka'];
        }

        // Validasi bulan jika monthly
        if (($filters['period_type'] ?? '') === 'monthly' && (!isset($filters['month']) || !is_numeric($filters['month']) || $filters['month'] < 1 || $filters['month'] > 12)) {
            $errors['month'] = ['Bulan harus diisi dan antara 1-12 untuk periode monthly'];
        }

        // Validasi format
        if (isset($filters['format']) && !in_array($filters['format'], ['excel', 'pdf'])) {
            $errors['format'] = ['Format harus excel atau pdf'];
        }

        if (!empty($errors)) {
            return $this->validationError($errors, 'Validasi filter laporan gagal');
        }

        return null;
    }

    /**
     * Generate file (Excel atau PDF)
     */
    private function generateFile(array $data, string $format, string $reportType)
    {
        $timestamp = Carbon::now()->format('Ymd_His');
        $periodInfo = $this->getPeriodInfo($data['filters'] ?? []);

        if ($format === 'excel') {
            return $this->generateExcelFile($data, $reportType, $timestamp, $periodInfo);
        } else {
            return $this->generatePdfFile($data, $reportType, $timestamp, $periodInfo);
        }
    }

    /**
     * Generate Excel file
     */
    private function generateExcelFile(array $data, string $reportType, string $timestamp, array $periodInfo)
    {
        $fileName = "{$reportType}_report_{$periodInfo['file_suffix']}_{$timestamp}.xlsx";
        $filePath = storage_path("app/reports/{$fileName}");

        // Buat directory jika belum ada
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        // Generate Excel (gunakan Maatwebsite/Excel)
        $exportClass = $this->getExportClass($reportType);
        Excel::store(new $exportClass($data), "reports/{$fileName}");

        return [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
    }

    /**
     * Generate PDF file
     */
    private function generatePdfFile(array $data, string $reportType, string $timestamp, array $periodInfo)
    {
        $fileName = "{$reportType}_report_{$periodInfo['file_suffix']}_{$timestamp}.pdf";
        $filePath = storage_path("app/reports/{$fileName}");

        // Buat directory jika belum ada
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        // Generate PDF view
        $viewName = $this->getPdfViewName($reportType);
        $pdf = Pdf::loadView($viewName, ['data' => $data, 'periodInfo' => $periodInfo]);

        // Simpan file
        $pdf->save($filePath);

        return [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf'
        ];
    }

    /**
     * Get export class berdasarkan report type
     */
    private function getExportClass(string $reportType)
    {
        $classes = [
            'spp' => \App\Exports\SppReportExport::class,
            'savings' => \App\Exports\SavingsReportExport::class,
            'financial_summary' => \App\Exports\FinancialSummaryExport::class,
        ];

        return $classes[$reportType] ?? $classes['spp'];
    }

    /**
     * Get PDF view name
     */
    private function getPdfViewName(string $reportType)
    {
        $views = [
            'spp' => 'reports.spp',
            'savings' => 'reports.savings',
            'financial_summary' => 'reports.financial_summary',
        ];

        return $views[$reportType] ?? 'reports.spp';
    }

    /**
     * Get period info untuk nama file
     */
    private function getPeriodInfo(array $filters)
    {
        $periodType = $filters['period_type'] ?? 'monthly';
        $year = $filters['year'] ?? date('Y');
        $month = $filters['month'] ?? date('m');

        // Pastikan $month adalah integer
        $month = (int)$month;

        if ($periodType === 'monthly') {
            $monthName = \Carbon\Carbon::create()->month($month)->locale('id')->monthName;
            return [
                'display' => "Bulan {$monthName} {$year}",
                'file_suffix' => "{$year}_{$month}"
            ];
        } else {
            return [
                'display' => "Tahun {$year}",
                'file_suffix' => "{$year}"
            ];
        }
    }

    /**
     * Simpan log laporan
     */
    private function saveReportLog(array $data)
    {
        return $this->reportRepository->createReportLog($data);
    }

    /**
     * Get report history
     */
    public function getReportHistory(array $filters, int $userId)
    {
        try {
            // Hanya ambil history user yang bersangkutan
            $filters['user_id'] = $userId;

            $history = $this->reportRepository->getReportHistory($filters);

            return $this->success($history, 'Riwayat laporan berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil riwayat laporan', $e);
        }
    }
}
