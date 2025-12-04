<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use App\Repositories\Eloquent\StudentRepository;
use App\Repositories\Interfaces\SppPaymentRepositoryInterface;
use App\Repositories\Eloquent\SppPaymentRepository;
use App\Repositories\Interfaces\SppSettingRepositoryInterface;
use App\Repositories\Eloquent\SppSettingRepository;
use App\Repositories\Interfaces\DashboardRepositoryInterface;
use App\Repositories\Eloquent\DashboardRepository;
use App\Repositories\Interfaces\SavingsRepositoryInterface;
use App\Repositories\Eloquent\SavingsRepository;
use App\Repositories\Interfaces\ParentRepositoryInterface;
use App\Repositories\Eloquent\ParentRepository;
use App\Repositories\Interfaces\AcademicYearRepositoryInterface;
use App\Repositories\Eloquent\AcademicYearRepository;
use App\Repositories\Interfaces\ParentFinanceRepositoryInterface;
use App\Repositories\Eloquent\ParentFinanceRepository;
use App\Repositories\Interfaces\FinanceReportRepositoryInterface;
use App\Repositories\Eloquent\FinanceReportRepository;
use App\Repositories\Interfaces\ScholarshipRepositoryInterface;
use App\Repositories\Eloquent\ScholarshipRepository;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StudentRepositoryInterface::class, StudentRepository::class);
        $this->app->bind(SppPaymentRepositoryInterface::class, SppPaymentRepository::class);
        $this->app->bind(SppSettingRepositoryInterface::class, SppSettingRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $this->app->bind(SavingsRepositoryInterface::class, SavingsRepository::class);
        $this->app->bind(ParentRepositoryInterface::class, ParentRepository::class);
        $this->app->bind(AcademicYearRepositoryInterface::class, AcademicYearRepository::class);
        $this->app->bind(ParentFinanceRepositoryInterface::class, ParentFinanceRepository::class);
        $this->app->bind(FinanceReportRepositoryInterface::class, FinanceReportRepository::class);
        $this->app->bind(ScholarshipRepositoryInterface::class, ScholarshipRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
