<?php
// app/Repositories/Interfaces/FinanceReportRepositoryInterface.php

namespace App\Repositories\Interfaces;

interface FinanceReportRepositoryInterface
{
    public function getSppReportData(array $filters): array;
    public function getSavingsReportData(array $filters): array;
    public function createReportLog(array $data);
    public function getReportHistory(array $filters);
    public function cleanupOldReports(int $days = 7): int;
}
