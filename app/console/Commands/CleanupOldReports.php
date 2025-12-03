<?php
// app/Console/Commands/CleanupOldReports.php

namespace App\Console\Commands;

use App\Repositories\Interfaces\FinanceReportRepositoryInterface;
use Illuminate\Console\Command;

class CleanupOldReports extends Command
{
    protected $signature = 'reports:cleanup {--days=7 : Days to keep reports}';
    protected $description = 'Clean up old report files and logs';

    public function handle(FinanceReportRepositoryInterface $reportRepository)
    {
        $days = $this->option('days');
        $deletedCount = $reportRepository->cleanupOldReports($days);

        $this->info("Cleaned up {$deletedCount} old report files and logs (older than {$days} days).");

        return Command::SUCCESS;
    }
}
