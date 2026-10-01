<?php

use App\Http\Controllers\AccountabilityController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\ProcessTemplateController;
use App\Http\Controllers\Admin\TelegramEmployeeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ProcessEscalationController;
use App\Http\Controllers\ProcessExecutionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RecurringTaskController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskReminderController;
use App\Http\Controllers\TelegramSettingsController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('projects', ProjectController::class);
    Route::resource('tasks', TaskController::class);

    // Task reminders nested under tasks
    Route::post('/tasks/{task}/reminders', [TaskReminderController::class, 'store'])->name('tasks.reminders.store');
    Route::delete('/tasks/{task}/reminders/{reminder}', [TaskReminderController::class, 'destroy'])->name('tasks.reminders.destroy');

    // Reminder centre
    Route::get('/reminders', [TaskReminderController::class, 'index'])->name('reminders.index');

    // Notification centre
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Admin-only user management
    Route::middleware([EnsureUserIsAdmin::class])->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
    });

    // Recurring Tasks
    Route::resource('recurring-tasks', RecurringTaskController::class)->except(['destroy']);
    Route::patch('/recurring-tasks/{recurringTask}/pause', [RecurringTaskController::class, 'pause'])->name('recurring-tasks.pause');
    Route::patch('/recurring-tasks/{recurringTask}/resume', [RecurringTaskController::class, 'resume'])->name('recurring-tasks.resume');
    Route::patch('/recurring-tasks/{recurringTask}/cancel', [RecurringTaskController::class, 'cancel'])->name('recurring-tasks.cancel');

    // Processes & Checklists
    Route::resource('processes', ProcessController::class)->except(['destroy']);
    Route::patch('/processes/{process}/pause', [ProcessController::class, 'pause'])->name('processes.pause');
    Route::patch('/processes/{process}/resume', [ProcessController::class, 'resume'])->name('processes.resume');
    Route::patch('/processes/{process}/cancel', [ProcessController::class, 'cancel'])->name('processes.cancel');

    // Process Executions / Checklists
    Route::get('/process-executions', [ProcessExecutionController::class, 'index'])->name('process-executions.index');
    Route::get('/my-checklists', [ProcessExecutionController::class, 'index'])->name('my-checklists');
    Route::get('/process-executions/{execution}', [ProcessExecutionController::class, 'show'])->name('process-executions.show');
    Route::post('/process-executions/{execution}/save-progress', [ProcessExecutionController::class, 'saveProgress'])->name('process-executions.save-progress');
    Route::post('/process-executions/{execution}/confirm', [ProcessExecutionController::class, 'confirm'])->name('process-executions.confirm');

    // Accountability & Management Monitoring
    Route::prefix('accountability')->name('accountability.')->group(function () {
        Route::get('/departments', [AccountabilityController::class, 'departments'])->name('departments.index');
        Route::get('/departments/{department}', [AccountabilityController::class, 'departmentDetail'])->name('departments.show');
        Route::get('/users', [AccountabilityController::class, 'users'])->name('users.index');
        Route::get('/users/{user}', [AccountabilityController::class, 'userDetail'])->name('users.show');
        Route::get('/processes', [AccountabilityController::class, 'processes'])->name('processes.index');
        Route::get('/processes/{process}', [AccountabilityController::class, 'processDetail'])->name('processes.show');
        Route::get('/escalations', [ProcessEscalationController::class, 'index'])->name('escalations.index');
        Route::post('/escalations/{event}/acknowledge', [ProcessEscalationController::class, 'acknowledge'])->name('escalations.acknowledge');
    });

    // Business Intelligence & Management Reports (Phase 8)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/departments', [ReportController::class, 'departments'])->name('departments.index');
        Route::get('/departments/{department}', [ReportController::class, 'departmentDetail'])->name('departments.show');
        Route::get('/processes', [ReportController::class, 'processes'])->name('processes.index');
        Route::get('/processes/{process}', [ReportController::class, 'processDetail'])->name('processes.show');
        Route::get('/escalations', [ReportController::class, 'escalations'])->name('escalations.index');
    });

    // Telegram Settings
    Route::get('/settings/telegram', [TelegramSettingsController::class, 'edit'])->name('settings.telegram');
    Route::post('/settings/telegram/generate', [TelegramSettingsController::class, 'generateCode'])->name('settings.telegram.generate');
    Route::post('/settings/telegram/unlink', [TelegramSettingsController::class, 'unlink'])->name('settings.telegram.unlink');
    Route::post('/settings/telegram/preferences', [TelegramSettingsController::class, 'updatePreferences'])->name('settings.telegram.preferences');

    // Admin Telegram Status
    Route::get('/admin/telegram', [TelegramSettingsController::class, 'adminStatus'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.telegram');

    // Governance, Configuration, Audit & Multi-tenancy
    Route::prefix('admin')->name('admin.')->group(function () {
        // Organization settings (Admin only)
        Route::middleware([EnsureUserIsAdmin::class])->group(function () {
            Route::get('/organization', [OrganizationController::class, 'show'])->name('organization.show');
            Route::get('/organization/edit', [OrganizationController::class, 'edit'])->name('organization.edit');
            Route::put('/organization', [OrganizationController::class, 'update'])->name('organization.update');

            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

            Route::resource('departments', DepartmentController::class);
            Route::patch('/departments/{department}/toggle-active', [DepartmentController::class, 'toggleActive'])->name('departments.toggle-active');
        });

        // Telegram Employees (Admin & Manager)
        Route::resource('telegram-employees', TelegramEmployeeController::class);
        Route::patch('/telegram-employees/{telegram_employee}/toggle-active', [TelegramEmployeeController::class, 'toggleActive'])->name('telegram-employees.toggle-active');
        Route::post('/telegram-employees/{telegram_employee}/generate-code', [TelegramEmployeeController::class, 'generateCode'])->name('telegram-employees.generate-code');
        Route::post('/telegram-employees/{telegram_employee}/unlink', [TelegramEmployeeController::class, 'unlink'])->name('telegram-employees.unlink');

        // Process templates governance (Admin & Manager)
        Route::resource('process-templates', ProcessTemplateController::class);
        Route::patch('/process-templates/{process_template}/toggle-active', [ProcessTemplateController::class, 'toggleActive'])->name('process-templates.toggle-active');
    });
});

// Telegram Webhook (No session auth)
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);
