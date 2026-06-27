<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientIntakeController;
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
    Route::view('/access-pending', 'auth.access-pending')->name('access.pending');

    Route::middleware('active.staff')->group(function () {
        Route::get('/dashboard', DashboardController::class)
            ->middleware('permission:view dashboard')
            ->name('dashboard');

        Route::middleware('permission:manage clients')->group(function () {
            Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
            Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
            Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
            Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
            Route::get('/clients/{client}/details', [ClientController::class, 'editDetails'])->name('clients.details.edit');
            Route::put('/clients/{client}/details', [ClientController::class, 'updateDetails'])->name('clients.details.update');
        });

        Route::middleware('permission:manage intakes')->group(function () {
            Route::get('/intakes', [ClientIntakeController::class, 'index'])->name('intakes.index');
            Route::get('/intakes/create', [ClientIntakeController::class, 'create'])->name('intakes.create');
            Route::post('/intakes', [ClientIntakeController::class, 'store'])->name('intakes.store');
            Route::get('/intakes/{intake}', [ClientIntakeController::class, 'show'])->name('intakes.show');
            Route::patch('/intakes/{intake}/conflict-review', [ClientIntakeController::class, 'reviewConflict'])->name('intakes.conflict-review');
            Route::post('/intakes/{intake}/convert-matter', [ClientIntakeController::class, 'convertToMatter'])->name('intakes.convert-matter');
        });

        Route::middleware('permission:manage matters')->group(function () {
            Route::get('/matters', [MatterController::class, 'index'])->name('matters.index');
            Route::get('/matters/create', [MatterController::class, 'create'])->name('matters.create');
            Route::post('/matters', [MatterController::class, 'store'])->name('matters.store');
            Route::get('/clients/{client}/engagements/create', [MatterController::class, 'createForClient'])->name('clients.engagements.create');
            Route::post('/clients/{client}/engagements', [MatterController::class, 'storeForClient'])->name('clients.engagements.store');
            Route::get('/matters/{matter}', [MatterController::class, 'show'])->name('matters.show');
            Route::patch('/matters/{matter}/engagement', [MatterController::class, 'updateEngagement'])->name('matters.engagement.update');
        });

        Route::get('/recoveries', [RecoveryController::class, 'index'])
            ->middleware('permission:manage recoveries')
            ->name('recoveries.index');
        Route::get('/land-titles', [LandTitleController::class, 'index'])
            ->middleware('permission:manage land titles')
            ->name('land-titles.index');
        Route::get('/finance', [FinanceController::class, 'index'])
            ->middleware('permission:manage finance')
            ->name('finance.index');
        Route::get('/staff', [StaffController::class, 'index'])
            ->middleware('permission:manage staff')
            ->name('staff.index');

        Route::prefix('access-control')->name('access.')->middleware('permission:manage access control')->group(function () {
            Route::get('/users', [AccessControlController::class, 'users'])->name('users.index');
            Route::put('/users/{user}', [AccessControlController::class, 'updateUser'])->name('users.update');
            Route::patch('/users/{user}/approve', [AccessControlController::class, 'approveUser'])->name('users.approve');
            Route::delete('/users/{user}', [AccessControlController::class, 'destroyUser'])->name('users.destroy');
            Route::get('/approvals', [AccessControlController::class, 'approvals'])->name('approvals.index');
            Route::get('/roles', [AccessControlController::class, 'roles'])->name('roles.index');
            Route::post('/roles', [AccessControlController::class, 'storeRole'])->name('roles.store');
            Route::put('/roles/{role}', [AccessControlController::class, 'updateRole'])->name('roles.update');
            Route::delete('/roles/{role}', [AccessControlController::class, 'destroyRole'])->name('roles.destroy');
            Route::get('/permissions', [AccessControlController::class, 'permissions'])->name('permissions.index');
            Route::post('/permissions', [AccessControlController::class, 'storePermission'])->name('permissions.store');
            Route::put('/permissions/{permission}', [AccessControlController::class, 'updatePermission'])->name('permissions.update');
            Route::delete('/permissions/{permission}', [AccessControlController::class, 'destroyPermission'])->name('permissions.destroy');
        });

        Route::middleware('permission:manage settings')->group(function () {
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
    });
});
