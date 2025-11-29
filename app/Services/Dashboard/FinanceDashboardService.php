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
            return $this->serverError('Gagal mengambil data dashboard: ' . $e->getMessage(), null, 500);
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
                'total_deposits' => $this->dashboardRepository->getTotalSavingsDepositsThisMonth($year, $month),
                'total_withdrawals' => $this->dashboardRepository->getTotalSavingsWithdrawalsThisMonth($year, $month),
                'current_balance' => $this->dashboardRepository->getTotalSavingsBalance(),
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
        $savingsMonthlyData = $this->dashboardRepository->getSavingsMonthlyData($year);

        return [
            'spp_monthly' => [
                'labels' => array_column($sppMonthlyData, 'month_name'),
                'data' => array_column($sppMonthlyData, 'total')
            ],
            'savings_trend' => [
                'labels' => array_column($savingsMonthlyData, 'month_name'),
                'deposits' => array_column($savingsMonthlyData, 'deposits'),
                'withdrawals' => array_column($savingsMonthlyData, 'withdrawals')
            ],
            'students_by_class' => $this->dashboardRepository->getStudentCountByClass()
        ];
    }

    private function getRecentActivities(): array
    {
        $recentPayments = $this->dashboardRepository->getRecentPayments(3);
        $recentSavings = $this->dashboardRepository->getRecentSavingsTransactions(2);

        $activities = [];

        // Gabungkan aktivitas SPP dan tabungan
        foreach ($recentPayments as $payment) {
            $activities[] = [
                'type' => 'spp_payment',
                'title' => 'Pembayaran SPP',
                'description' => "{$payment['student_name']} - {$payment['class']}",
                'amount' => $payment['amount'],
                'receipt_number' => $payment['receipt_number'],
                'date' => $payment['payment_date'],
                'time' => Carbon::parse($payment['payment_date'])->format('H:i'),
                'user' => $payment['created_by']
            ];
        }

        foreach ($recentSavings as $savings) {
            $activities[] = [
                'type' => 'savings_' . $savings['transaction_type'],
                'title' => $savings['transaction_type'] === 'deposit' ? 'Setoran Tabungan' : 'Penarikan Tabungan',
                'description' => "{$savings['student_name']} - {$savings['class']}",
                'amount' => $savings['amount'],
                'receipt_number' => $savings['transaction_number'],
                'date' => $savings['transaction_date'],
                'time' => Carbon::parse($savings['transaction_date'])->format('H:i'),
                'user' => $savings['created_by']
            ];
        }

        // Urutkan berdasarkan tanggal terbaru
        usort($activities, function($a, $b) {
            return strtotime($b['date'] . ' ' . $b['time']) - strtotime($a['date'] . ' ' . $a['time']);
        });

        // Ambil 5 aktivitas terbaru
        return array_slice($activities, 0, 5);
    }
}
