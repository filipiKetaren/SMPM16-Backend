<?php
// app/Http/Controllers/Finance/FinanceReportController.php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use App\Repositories\Eloquent\FinanceReportRepository;

class FinanceReportController extends Controller
{
    private $reportRepository;

    public function __construct()
    {
        $this->reportRepository = new FinanceReportRepository();
    }

    /**
     * Get SPP Report Data (hanya data, tanpa file)
     */
    public function getSppReportData(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $filters = $request->all();

        try {
            // Validasi filter
            $validationResult = $this->validateReportFilters($filters);
            if ($validationResult) {
                return response()->json($validationResult, 400);
            }

            // Dapatkan data dari repository
            $reportData = $this->reportRepository->getSppReportData($filters);

            // Simpan log bahwa data laporan diakses
            $this->saveAccessLog([
                'user_id' => $user->id,
                'report_type' => 'spp',
                'period_type' => $filters['period_type'] ?? 'monthly',
                'year' => $filters['year'] ?? date('Y'),
                'month' => $filters['month'] ?? null,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Data laporan SPP berhasil diambil',
                'data' => $reportData,
                'period_info' => $this->getPeriodInfo($filters),
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getSppReportData: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data laporan SPP',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get Savings Report Data (hanya data, tanpa file)
     */
    public function getSavingsReportData(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $filters = $request->all();

        try {
            // Validasi filter
            $validationResult = $this->validateReportFilters($filters);
            if ($validationResult) {
                return response()->json($validationResult, 400);
            }

            // Dapatkan data dari repository
            $reportData = $this->reportRepository->getSavingsReportData($filters);

            // Simpan log bahwa data laporan diakses
            $this->saveAccessLog([
                'user_id' => $user->id,
                'report_type' => 'savings',
                'period_type' => $filters['period_type'] ?? 'monthly',
                'year' => $filters['year'] ?? date('Y'),
                'month' => $filters['month'] ?? null,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Data laporan Tabungan berhasil diambil',
                'data' => $reportData,
                'period_info' => $this->getPeriodInfo($filters),
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getSavingsReportData: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data laporan Tabungan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get Financial Summary Report Data (hanya data, tanpa file)
     */
    public function getFinancialSummaryData(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $filters = $request->all();

        try {
            // Validasi filter
            $validationResult = $this->validateReportFilters($filters);
            if ($validationResult) {
                return response()->json($validationResult, 400);
            }

            // Dapatkan data SPP dan Tabungan
            $sppData = $this->reportRepository->getSppReportData($filters);
            $savingsData = $this->reportRepository->getSavingsReportData($filters);

            // Gabungkan data untuk summary
            $summaryData = [
                'spp' => $sppData,
                'savings' => $savingsData,
                'total_finance' => [
                    'total_income' => ($sppData['summary']['total_income'] ?? 0) + ($savingsData['summary']['total_deposits'] ?? 0),
                    'total_expenditure' => ($savingsData['summary']['total_withdrawals'] ?? 0),
                    'net_income' => (($sppData['summary']['total_income'] ?? 0) + ($savingsData['summary']['total_deposits'] ?? 0)) - ($savingsData['summary']['total_withdrawals'] ?? 0)
                ]
            ];

            // Simpan log bahwa data laporan diakses
            $this->saveAccessLog([
                'user_id' => $user->id,
                'report_type' => 'financial_summary',
                'period_type' => $filters['period_type'] ?? 'monthly',
                'year' => $filters['year'] ?? date('Y'),
                'month' => $filters['month'] ?? null,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Data ringkasan keuangan berhasil diambil',
                'data' => $summaryData,
                'period_info' => $this->getPeriodInfo($filters),
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getFinancialSummaryData: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data ringkasan keuangan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get report history/logs
     */
    public function reportHistory(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $filters = $request->all();

        try {
            // Hanya ambil history user yang bersangkutan
            $filters['user_id'] = $user->id;

            $history = $this->reportRepository->getReportHistory($filters);

            return response()->json([
                'status' => 'success',
                'message' => 'Riwayat laporan berhasil diambil',
                'data' => $history
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error reportHistory: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil riwayat laporan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Validasi filter (helper method)
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

        if (!empty($errors)) {
            return [
                'status' => 'error',
                'message' => 'Validasi filter gagal',
                'errors' => $errors
            ];
        }

        return null;
    }

    /**
     * Get period info
     */
    private function getPeriodInfo(array $filters)
    {
        $periodType = $filters['period_type'] ?? 'monthly';
        $year = $filters['year'] ?? date('Y');
        $month = $filters['month'] ?? date('m');

        // Pastikan $month adalah integer
        $month = (int)$month;
        $year = (int)$year;

        if ($periodType === 'monthly') {
            $monthName = \Carbon\Carbon::create()->month($month)->locale('id')->monthName;
            return [
                'display' => "Bulan {$monthName} {$year}",
                'file_suffix' => "{$year}_{$month}",
                'period_type' => 'monthly',
                'year' => $year,
                'month' => $month,
                'month_name' => $monthName
            ];
        } else {
            return [
                'display' => "Tahun {$year}",
                'file_suffix' => "{$year}",
                'period_type' => 'yearly',
                'year' => $year
            ];
        }
    }

    /**
     * Simpan log akses data
     */
    private function saveAccessLog(array $data)
    {
        try {
            \App\Models\ReportAccessLog::create([
                'user_id' => $data['user_id'],
                'report_type' => $data['report_type'],
                'period_type' => $data['period_type'],
                'year' => $data['year'],
                'month' => $data['month'] ?? null,
                'accessed_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log error tapi tidak mengganggu response utama
            Log::error('Gagal menyimpan log akses: ' . $e->getMessage());
        }
    }
}
