<?php

namespace App\Repositories\Interfaces;

interface DashboardRepositoryInterface
{
    public function getTotalSppThisMonth(int $year, int $month): float;
    public function getTotalSppThisYear(int $year): float;
    public function getUnpaidStudentsCount(int $year, int $month): int;
    public function getTotalActiveStudents(): int;
    public function getTotalAlumniStudents(): int;
    public function getSppMonthlyData(int $year): array;
    public function getRecentPayments(int $limit = 5): array;
    public function getStudentCountByClass(): array;
}
