<?php

namespace App\Services\Dashboard;

use App\Repositories\Interfaces\DashboardRepositoryInterface;
use App\Services\BaseService;
use Carbon\Carbon;

class FinanceDashboardService extends BaseService
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository
    ) {}

    public function getDashboardData()
    {
        try {
            $currentYear = Carbon::now()->year;
            $currentMonth = Carbon::now()->month;

            // Data statistik utama
            $stats = $this->getMainStats($currentYear, $currentMonth);

            // Data charts
            $charts = $this->getChartsData($currentYear);

            // Aktivitas terbaru
            $recentActivities = $this->getRecentActivities();

            $data = [
                'period' => [
                    'month' => $currentMonth,
                    'year' => $currentYear,
                    'month_name' => Carbon::now()->locale('id')->monthName,
                ],
                'stats' => $stats,
                'charts' => $charts,
                'recent_activities' => $recentActivities
            ];

            return $this->success($data, 'Data dashboard berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data dashboard: ' . $e->getMessage(), null, 500);
        }
    }

    private function getMainStats(int $year, int $month): array
    {
        return [
            'spp' => [
                'total_this_month' => $this->dashboardRepository->getTotalSppThisMonth($year, $month),
                'total_this_year' => $this->dashboardRepository->getTotalSppThisYear($year),
                'unpaid_students' => $this->dashboardRepository->getUnpaidStudentsCount($year, $month),
            ],
            'savings' => [
                'total_deposits' => 0, // Akan diimplementasikan nanti
                'total_withdrawals' => 0,
                'current_balance' => 0,
            ],
            'students' => [
                'total_active' => $this->dashboardRepository->getTotalActiveStudents(),
                'total_alumni' => $this->dashboardRepository->getTotalAlumniStudents(),
                'by_class' => $this->dashboardRepository->getStudentCountByClass(),
            ]
        ];
    }

    private function getChartsData(int $year): array
    {
        $sppMonthlyData = $this->dashboardRepository->getSppMonthlyData($year);

        return [
            'spp_monthly' => [
                'labels' => array_column($sppMonthlyData, 'month_name'),
                'data' => array_column($sppMonthlyData, 'total')
            ],
            'savings_trend' => [
                'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                'deposits' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], // Dummy data untuk sekarang
                'withdrawals' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
            ],
            'students_by_class' => $this->dashboardRepository->getStudentCountByClass()
        ];
    }

    private function getRecentActivities(): array
    {
        $recentPayments = $this->dashboardRepository->getRecentPayments(5);

        return array_map(function ($payment) {
            return [
                'type' => 'spp_payment',
                'title' => 'Pembayaran SPP',
                'description' => "{$payment['student_name']} - {$payment['class']}",
                'amount' => $payment['amount'],
                'receipt_number' => $payment['receipt_number'],
                'date' => $payment['payment_date'],
                'time' => Carbon::parse($payment['payment_date'])->format('H:i'),
                'user' => $payment['created_by']
            ];
        }, $recentPayments);
    }
}
