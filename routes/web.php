<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\LandTitleController;
use App\Http\Controllers\MatterController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SystemSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/matters', [MatterController::class, 'index'])->name('matters.index');
    Route::get('/recoveries', [RecoveryController::class, 'index'])->name('recoveries.index');
    Route::get('/land-titles', [LandTitleController::class, 'index'])->name('land-titles.index');
    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::get('/settings/system', [SystemSettingController::class, 'overview'])->name('settings.system.overview');
    Route::get('/settings/system/{setting}', [SystemSettingController::class, 'index'])->name('settings.system.index');
    Route::get('/settings/system/{setting}/create', [SystemSettingController::class, 'create'])->name('settings.system.create');
    Route::post('/settings/system/{setting}', [SystemSettingController::class, 'store'])->name('settings.system.store');
    Route::get('/settings/system/{setting}/{record}/edit', [SystemSettingController::class, 'edit'])->name('settings.system.edit');
    Route::put('/settings/system/{setting}/{record}', [SystemSettingController::class, 'update'])->name('settings.system.update');
    Route::delete('/settings/system/{setting}/{record}', [SystemSettingController::class, 'destroy'])->name('settings.system.destroy');
    Route::get('/settings/company', [CompanySettingController::class, 'edit'])->name('settings.company.edit');
    Route::put('/settings/company', [CompanySettingController::class, 'update'])->name('settings.company.update');
});
