<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientAdrController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientFileController;
use App\Http\Controllers\ClientIntakeController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\LandTitleController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\LitigationController;
use App\Http\Controllers\MatterController;
use App\Http\Controllers\MatterBillingController;
use App\Http\Controllers\MatterInstructionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PettyCashController;
use App\Http\Controllers\PublicHolidayController;
use App\Http\Controllers\RecoveryActivityController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\RecoveryReportController;
use App\Http\Controllers\RequisitionController;
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
    // Pending users must always reach the pending screen, even without an
    // active staff profile and without any module permission.
    Route::view('/access-pending', 'auth.access-pending')->name('access.pending');

        // Document downloads from the storage bucket (auth + active staff, no per-route permission).
        Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
            ->middleware('active.staff')
            ->name('attachments.download');
        Route::get('/attachments/{attachment}/view', [AttachmentController::class, 'view'])
            ->middleware('active.staff')
            ->name('attachments.view');
    // `php artisan kfms:sync-route-permissions` to create a matching
    // permission record.
    Route::middleware(['active.staff', 'route.permission'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Internal messages
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
        Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{conversation}/reply', [MessageController::class, 'reply'])->name('messages.reply');
        Route::patch('/messages/{conversation}/read', [MessageController::class, 'markRead'])->name('messages.read');

        // Clients
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/export', [ClientController::class, 'export'])->name('clients.export');
        Route::post('/clients/import', [ClientController::class, 'import'])->name('clients.import');
        Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::get('/clients/{client}/details', [ClientController::class, 'editDetails'])->name('clients.details.edit');
        Route::put('/clients/{client}/details', [ClientController::class, 'updateDetails'])->name('clients.details.update');
        Route::get('/clients/{client}/adr/create', [ClientAdrController::class, 'create'])->name('clients.adr.create');
        Route::post('/clients/{client}/adr', [ClientAdrController::class, 'store'])->name('clients.adr.store');
        Route::get('/clients/{client}/files/create', [ClientFileController::class, 'create'])->name('clients.files.create');
        Route::post('/clients/{client}/files', [ClientFileController::class, 'store'])->name('clients.files.store');
        Route::get('/files/{file}', [ClientFileController::class, 'show'])->name('clients.files.show');
        Route::post('/files/{file}/documents', [ClientFileController::class, 'storeDocument'])->name('clients.files.documents.store');
        Route::get('/adr/{adr}', [ClientAdrController::class, 'show'])->name('clients.adr.show');
        Route::get('/clients/{client}/matter/create', [MatterController::class, 'createForClient'])->name('clients.matters.create');
        Route::post('/clients/{client}/matter', [MatterController::class, 'storeForClient'])->name('clients.matters.store');

        // Client intakes
        Route::get('/intakes', [ClientIntakeController::class, 'index'])->name('intakes.index');
        Route::get('/intakes/create', [ClientIntakeController::class, 'create'])->name('intakes.create');
        Route::post('/intakes', [ClientIntakeController::class, 'store'])->name('intakes.store');
        Route::get('/intakes/{intake}', [ClientIntakeController::class, 'show'])->name('intakes.show');
        Route::patch('/intakes/{intake}/review', [ClientIntakeController::class, 'review'])->name('intakes.review');

        // Matters
        Route::get('/matters', [MatterController::class, 'index'])->name('matters.index');
        Route::get('/matters/create', [MatterController::class, 'create'])->name('matters.create');
        Route::post('/matters', [MatterController::class, 'store'])->name('matters.store');
        Route::get('/matters/export', [MatterController::class, 'export'])->name('matters.export');
        Route::post('/matters/import', [MatterController::class, 'import'])->name('matters.import');
        Route::get('/matters/{matter}/billing', [MatterBillingController::class, 'show'])->name('matters.billing.show');
        Route::post('/matters/{matter}/billing/invoices', [MatterBillingController::class, 'storeInvoice'])->name('matters.billing.invoices.store');
        Route::post('/matters/{matter}/billing/costs', [MatterBillingController::class, 'storeCost'])->name('matters.billing.costs.store');
        Route::get('/matters/{matter}/instructions', [MatterInstructionController::class, 'show'])->name('matters.instructions.show');
        Route::patch('/matters/{matter}/instructions', [MatterInstructionController::class, 'update'])->name('matters.instructions.update');
        Route::post('/matters/{matter}/documents', [MatterInstructionController::class, 'storeDocument'])->name('matters.documents.store');
        Route::get('/matters/{matter}', [MatterController::class, 'show'])->name('matters.show');

        // Litigation (court diary)
        Route::get('/litigation/dashboard', [LitigationController::class, 'dashboard'])->name('litigation.dashboard');
        Route::get('/litigation', [LitigationController::class, 'index'])->name('litigation.index');
        Route::get('/litigation/create', [LitigationController::class, 'create'])->name('litigation.create');
        Route::post('/litigation', [LitigationController::class, 'store'])->name('litigation.store');
        Route::get('/litigation/export', [LitigationController::class, 'export'])->name('litigation.export');
        Route::post('/litigation/import', [LitigationController::class, 'import'])->name('litigation.import');
        Route::get('/litigation/{litigation}', [LitigationController::class, 'show'])->name('litigation.show');
        Route::get('/litigation/{litigation}/edit', [LitigationController::class, 'edit'])->name('litigation.edit');
        Route::put('/litigation/{litigation}', [LitigationController::class, 'update'])->name('litigation.update');
        Route::patch('/litigation/{litigation}/outcome', [LitigationController::class, 'recordOutcome'])->name('litigation.outcome');

        // Firm calendar (meetings, court dates, holidays) — visible firm-wide, branch-scoped
        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
        Route::get('/calendar/create', [CalendarController::class, 'create'])->name('calendar.create');
        Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store');
        Route::get('/calendar/{calendar}', [CalendarController::class, 'show'])->name('calendar.show');
        Route::get('/calendar/{calendar}/edit', [CalendarController::class, 'edit'])->name('calendar.edit');
        Route::put('/calendar/{calendar}', [CalendarController::class, 'update'])->name('calendar.update');
        Route::delete('/calendar/{calendar}', [CalendarController::class, 'destroy'])->name('calendar.destroy');

        // Standalone module landing pages
        // Recoveries (debt collection) — manager registers debts & assigns
        // officers; officers report demands and money recovered.
        Route::get('/recoveries', [RecoveryController::class, 'index'])->name('recoveries.index');
        Route::get('/recoveries/mine', [RecoveryController::class, 'mine'])->name('recoveries.mine');
        Route::get('/recoveries/reports', [RecoveryReportController::class, 'index'])->name('recoveries.reports');
        Route::get('/recoveries/reports/export', [RecoveryReportController::class, 'export'])->name('recoveries.export');
        Route::get('/recoveries/create', [RecoveryController::class, 'create'])->name('recoveries.create');
        Route::post('/recoveries', [RecoveryController::class, 'store'])->name('recoveries.store');
        Route::get('/recoveries/{recovery}', [RecoveryController::class, 'show'])->name('recoveries.show');
        Route::get('/recoveries/{recovery}/edit', [RecoveryController::class, 'edit'])->name('recoveries.edit');
        Route::put('/recoveries/{recovery}', [RecoveryController::class, 'update'])->name('recoveries.update');
        Route::delete('/recoveries/{recovery}', [RecoveryController::class, 'destroy'])->name('recoveries.destroy');
        Route::post('/recoveries/{recovery}/activities', [RecoveryActivityController::class, 'store'])->name('recoveries.activities.store');
        Route::get('/land-titles', [LandTitleController::class, 'index'])->name('land-titles.index');
        Route::get('/land-titles/create', [LandTitleController::class, 'create'])->name('land-titles.create');
        Route::post('/land-titles', [LandTitleController::class, 'store'])->name('land-titles.store');
        Route::get('/land-titles/export', [LandTitleController::class, 'export'])->name('land-titles.export');
        Route::get('/land-titles/{landTitle}', [LandTitleController::class, 'show'])->name('land-titles.show');
        Route::get('/land-titles/{landTitle}/edit', [LandTitleController::class, 'edit'])->name('land-titles.edit');
        Route::put('/land-titles/{landTitle}', [LandTitleController::class, 'update'])->name('land-titles.update');
        Route::patch('/land-titles/{landTitle}/return', [LandTitleController::class, 'returnSecurity'])->name('land-titles.return');
        Route::delete('/land-titles/{landTitle}', [LandTitleController::class, 'destroy'])->name('land-titles.destroy');
        Route::get('/finance/dashboard', [FinanceController::class, 'dashboard'])->name('finance.dashboard');
        Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('/hr/dashboard', [HrController::class, 'dashboard'])->name('hr.dashboard');
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('staff.show');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');

        // Leave management
        Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
        Route::get('/leave/create', [LeaveController::class, 'create'])->name('leave.create');
        Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
        Route::get('/leave/{leave}', [LeaveController::class, 'show'])->name('leave.show');
        Route::patch('/leave/{leave}/approve', [LeaveController::class, 'approve'])->name('leave.approve');
        Route::patch('/leave/{leave}/reject', [LeaveController::class, 'reject'])->name('leave.reject');
        Route::patch('/leave/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');

        // Requisitions
        Route::get('/requisitions', [RequisitionController::class, 'index'])->name('requisitions.index');
        Route::get('/requisitions/create', [RequisitionController::class, 'create'])->name('requisitions.create');
        Route::post('/requisitions', [RequisitionController::class, 'store'])->name('requisitions.store');
        Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show'])->name('requisitions.show');
        Route::patch('/requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])->name('requisitions.approve');
        Route::patch('/requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])->name('requisitions.reject');
        Route::patch('/requisitions/{requisition}/cancel', [RequisitionController::class, 'cancel'])->name('requisitions.cancel');

        // Expenses (expenditure capture)
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');

        // Petty cash book
        Route::get('/petty-cash', [PettyCashController::class, 'index'])->name('petty-cash.index');
        Route::get('/petty-cash/create', [PettyCashController::class, 'create'])->name('petty-cash.create');
        Route::post('/petty-cash', [PettyCashController::class, 'store'])->name('petty-cash.store');

        // Expenditure ledger
        Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');

        // Access control
        Route::prefix('access-control')->name('access.')->group(function () {
            Route::get('/users', [AccessControlController::class, 'users'])->name('users.index');
            Route::put('/users/{user}', [AccessControlController::class, 'updateUser'])->name('users.update');
            Route::patch('/users/{user}/approve', [AccessControlController::class, 'approveUser'])->name('users.approve');
            Route::delete('/users/{user}', [AccessControlController::class, 'destroyUser'])->name('users.destroy');
            Route::get('/approvals', [AccessControlController::class, 'approvals'])->name('approvals.index');
            Route::get('/approvals/{profile}', [AccessControlController::class, 'showApproval'])->name('approvals.show');
            Route::get('/roles', [AccessControlController::class, 'roles'])->name('roles.index');
            Route::post('/roles', [AccessControlController::class, 'storeRole'])->name('roles.store');
            Route::put('/roles/{role}', [AccessControlController::class, 'updateRole'])->name('roles.update');
            Route::delete('/roles/{role}', [AccessControlController::class, 'destroyRole'])->name('roles.destroy');
            Route::get('/permissions', [AccessControlController::class, 'permissions'])->name('permissions.index');
            Route::post('/permissions', [AccessControlController::class, 'storePermission'])->name('permissions.store');
            Route::put('/permissions/{permission}', [AccessControlController::class, 'updatePermission'])->name('permissions.update');
            Route::delete('/permissions/{permission}', [AccessControlController::class, 'destroyPermission'])->name('permissions.destroy');
        });

        // Settings
        Route::get('/settings/system', [SystemSettingController::class, 'overview'])->name('settings.system.overview');
        Route::get('/settings/system/{setting}', [SystemSettingController::class, 'index'])->name('settings.system.index');
        Route::get('/settings/system/{setting}/create', [SystemSettingController::class, 'create'])->name('settings.system.create');
        Route::post('/settings/system/{setting}', [SystemSettingController::class, 'store'])->name('settings.system.store');
        Route::get('/settings/system/{setting}/{record}/edit', [SystemSettingController::class, 'edit'])->name('settings.system.edit');
        Route::put('/settings/system/{setting}/{record}', [SystemSettingController::class, 'update'])->name('settings.system.update');
        Route::delete('/settings/system/{setting}/{record}', [SystemSettingController::class, 'destroy'])->name('settings.system.destroy');
        Route::get('/settings/company', [CompanySettingController::class, 'edit'])->name('settings.company.edit');
        Route::put('/settings/company', [CompanySettingController::class, 'update'])->name('settings.company.update');

        // Branches
        Route::get('/settings/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::get('/settings/branches/create', [BranchController::class, 'create'])->name('branches.create');
        Route::post('/settings/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::get('/settings/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('/settings/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');

        // Public holidays
        Route::get('/settings/holidays', [PublicHolidayController::class, 'index'])->name('holidays.index');
        Route::get('/settings/holidays/create', [PublicHolidayController::class, 'create'])->name('holidays.create');
        Route::post('/settings/holidays', [PublicHolidayController::class, 'store'])->name('holidays.store');
        Route::get('/settings/holidays/{holiday}/edit', [PublicHolidayController::class, 'edit'])->name('holidays.edit');
        Route::put('/settings/holidays/{holiday}', [PublicHolidayController::class, 'update'])->name('holidays.update');
        Route::delete('/settings/holidays/{holiday}', [PublicHolidayController::class, 'destroy'])->name('holidays.destroy');
    });
});
