<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ParentAuthController;
use App\Http\Controllers\Dashboard\FinanceDashboardController;
use App\Http\Controllers\Finance\SppController;
use App\Http\Controllers\Finance\SavingsController;
use App\Http\Controllers\Finance\SppSettingController;
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\Parent\ParentFinanceController;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');

    Route::middleware('jwt')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        Route::post('register-admin', [AuthController::class, 'register'])->name('register.admin');
    });
});

// Auth routes untuk orang tua
Route::prefix('auth/parent')->name('auth.parent.')->group(function () {
    Route::post('login', [ParentAuthController::class, 'login'])->name('login');

    Route::middleware(['parent_auth'])->group(function () {
        Route::post('logout', [ParentAuthController::class, 'logout'])->name('logout');
        Route::get('me', [ParentAuthController::class, 'me'])->name('me');
        Route::post('refresh', [ParentAuthController::class, 'refresh'])->name('refresh');
    });
});

// Dashboard routes
Route::middleware(['jwt', 'finance_admin'])->prefix('dashboard')->group(function () {
    Route::get('/finance', [FinanceDashboardController::class, 'index']);
});

// SPP Management routes
Route::middleware(['jwt', 'finance_admin'])->prefix('finance')->group(function () {
    Route::prefix('spp')->group(function () {
        Route::get('/students', [SppController::class, 'getStudentsWithBills']);
        Route::get('/students/{id}/bills', [SppController::class, 'getStudentBills']);
        Route::get('/students/{id}/payment-history', [SppController::class, 'getStudentPaymentHistory']);
        Route::post('/payments/process', [SppController::class, 'processPayment']);

        Route::get('/payments/{id}', [SppController::class, 'getPaymentDetail']);
        Route::put('/payments/{id}', [SppController::class, 'updatePayment']);
        Route::delete('/payments/{id}', [SppController::class, 'deletePayment']);

         Route::prefix('spp-settings')->group(function () {
            Route::get('/', [SppSettingController::class, 'index']);
            Route::post('/', [SppSettingController::class, 'store']);
            Route::get('/active', [SppSettingController::class, 'getActiveSettings']);
            Route::get('/academic-year/{academicYearId}', [SppSettingController::class, 'getByAcademicYear']);
            Route::get('/{id}', [SppSettingController::class, 'show']);
            Route::put('/{id}', [SppSettingController::class, 'update']);
            Route::delete('/{id}', [SppSettingController::class, 'destroy']);
        });
    });

    // Tabungan routes
    Route::prefix('savings')->group(function () {
        Route::get('/students', [SavingsController::class, 'getAllStudentsWithSavings']);
        Route::get('/students/{id}', [SavingsController::class, 'getStudentSavings']);
        Route::post('/transactions/process', [SavingsController::class, 'processTransaction']);

        Route::get('/transactions/{id}', [SavingsController::class, 'getTransactionDetail']);
        Route::put('/transactions/{id}', [SavingsController::class, 'updateTransaction']);
        Route::delete('/transactions/{id}', [SavingsController::class, 'deleteTransaction']);
    });
});

Route::middleware(['jwt', 'finance_admin'])->prefix('academic-years')->group(function () {
        Route::get('/', [AcademicYearController::class, 'index']);
        Route::post('/', [AcademicYearController::class, 'store']);
        Route::get('/active', [AcademicYearController::class, 'getActiveAcademicYear']);
        Route::get('/{id}', [AcademicYearController::class, 'show']);
        Route::put('/{id}', [AcademicYearController::class, 'update']);
        Route::delete('/{id}', [AcademicYearController::class, 'destroy']);
    });

// Route untuk super admin (bisa ditambah later)
Route::middleware(['jwt'])->prefix('admin')->group(function () {
    // Route super admin nanti...
});

// Route untuk orang tua (nanti akan ditambah untuk monitoring)
Route::middleware(['parent_auth'])->prefix('parent')->group(function () {
    // Route untuk monitoring presensi, SPP, tabungan akan ditambah nanti
    Route::get('/dashboard', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Parent dashboard'
        ]);
    });

    Route::prefix('finance')->group(function () {
        Route::get('/history', [ParentFinanceController::class, 'getFinanceHistory']);
        Route::get('/students/{studentId}/detail', [ParentFinanceController::class, 'getStudentFinanceDetail']);
    });
});
