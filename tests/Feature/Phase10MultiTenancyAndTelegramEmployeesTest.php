<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessFrequency;
use App\Enums\ProcessResponseType;
use App\Enums\ProcessStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Organization;
use App\Models\Process;
use App\Models\ProcessExecution;
use App\Models\ProcessExecutionItem;
use App\Models\ProcessItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\TelegramAccount;
use App\Models\TelegramEmployee;
use App\Models\User;
use App\Services\OrganizationSeatService;
use App\Services\ProcessScheduleService;
use App\Services\TelegramAccountService;
use App\Services\TelegramUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase10MultiTenancyAndTelegramEmployeesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $adminB;

    private User $managerA;

    private Department $deptA;

    private Department $deptB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => 'test-token']);

        // Create Org A
        $this->orgA = Organization::firstOrCreate(
            ['code' => 'ORGA'],
            ['name' => 'Acme Corporation', 'max_web_users' => 5, 'is_active' => true]
        );

        // Create Org B
        $this->orgB = Organization::create([
            'name' => 'Beta Logistics',
            'code' => 'ORGB',
            'max_web_users' => 5,
            'is_active' => true,
        ]);

        // Create Admin A in Org A
        $this->adminA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        // Create Manager A in Org A
        $this->managerA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => UserRole::MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Create Admin B in Org B
        $this->adminB = User::factory()->create([
            'organization_id' => $this->orgB->id,
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        // Create Departments
        $this->deptA = Department::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Operations A',
            'code' => 'OPS-A',
            'is_active' => true,
        ]);

        $this->deptB = Department::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Operations B',
            'code' => 'OPS-B',
            'is_active' => true,
        ]);
    }

    // ==========================================
    // 1. ORGANIZATIONS & 5-SEAT WEB USER LIMITS
    // ==========================================

    public function test_organization_seat_service_calculates_seats_correctly(): void
    {
        $seatService = app(OrganizationSeatService::class);
        $usage = $seatService->getSeatUsage($this->orgA);

        $this->assertEquals(2, $usage['active_seats']); // adminA + managerA
        $this->assertEquals(5, $usage['max_seats']);
        $this->assertEquals(3, $usage['available_seats']);
        $this->assertFalse($usage['is_limit_reached']);
    }

    public function test_organization_allows_creating_up_to_5_active_web_users(): void
    {
        // We already have 2 active users (adminA + managerA). Add 3 more active users.
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->actingAs($this->adminA)->post(route('users.store'), [
                'name' => "User {$i}",
                'email' => "user{$i}@acme.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => UserRole::EMPLOYEE->value,
                'status' => UserStatus::ACTIVE->value,
            ]);
            $response->assertRedirect(route('users.index'));
        }

        $seatService = app(OrganizationSeatService::class);
        $usage = $seatService->getSeatUsage($this->orgA);

        $this->assertEquals(5, $usage['active_seats']);
        $this->assertEquals(0, $usage['available_seats']);
        $this->assertTrue($usage['is_limit_reached']);
    }

    public function test_organization_rejects_6th_active_web_user_creation(): void
    {
        // Fill 5 seats
        for ($i = 1; $i <= 3; $i++) {
            User::factory()->create([
                'organization_id' => $this->orgA->id,
                'status' => UserStatus::ACTIVE,
            ]);
        }

        // Attempt 6th active web user
        $response = $this->actingAs($this->adminA)->post(route('users.store'), [
            'name' => '6th User',
            'email' => 'sixth@acme.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::EMPLOYEE->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'sixth@acme.com']);
    }

    public function test_organization_allows_creating_inactive_user_even_when_5_seats_full(): void
    {
        // Fill 5 seats
        for ($i = 1; $i <= 3; $i++) {
            User::factory()->create([
                'organization_id' => $this->orgA->id,
                'status' => UserStatus::ACTIVE,
            ]);
        }

        // Create inactive web user (should succeed because inactive does not consume a seat)
        $response = $this->actingAs($this->adminA)->post(route('users.store'), [
            'name' => 'Inactive User',
            'email' => 'inactive@acme.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::EMPLOYEE->value,
            'status' => UserStatus::INACTIVE->value,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'inactive@acme.com', 'status' => UserStatus::INACTIVE->value]);
    }

    public function test_organization_rejects_activating_inactive_user_when_seats_are_full(): void
    {
        // Fill 5 seats
        for ($i = 1; $i <= 3; $i++) {
            User::factory()->create([
                'organization_id' => $this->orgA->id,
                'status' => UserStatus::ACTIVE,
            ]);
        }

        $inactiveUser = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => UserStatus::INACTIVE,
        ]);

        // Attempt to activate
        $response = $this->actingAs($this->adminA)->put(route('users.update', $inactiveUser), [
            'name' => $inactiveUser->name,
            'email' => $inactiveUser->email,
            'role' => $inactiveUser->role->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertEquals(UserStatus::INACTIVE, $inactiveUser->fresh()->status);
    }

    public function test_organization_allows_editing_existing_active_user_without_seat_error(): void
    {
        // Fill 5 seats
        for ($i = 1; $i <= 3; $i++) {
            User::factory()->create([
                'organization_id' => $this->orgA->id,
                'status' => UserStatus::ACTIVE,
            ]);
        }

        // Updating an existing active user should work smoothly
        $response = $this->actingAs($this->adminA)->put(route('users.update', $this->managerA), [
            'name' => 'Updated Manager Name',
            'email' => $this->managerA->email,
            'role' => $this->managerA->role->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertEquals('Updated Manager Name', $this->managerA->fresh()->name);
    }

    public function test_admin_can_view_and_update_organization_settings(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('admin.organization.show'));
        $response->assertOk();
        $response->assertSee('Acme Corporation');
        $response->assertSee('Web User Seats');

        $updateResponse = $this->actingAs($this->adminA)->put(route('admin.organization.update'), [
            'name' => 'Acme Global Enterprises',
            'contact_email' => 'admin@acmeglobal.com',
            'contact_phone' => '+1 555-0199',
            'timezone' => 'America/New_York',
        ]);

        $updateResponse->assertRedirect(route('admin.organization.show'));
        $this->assertEquals('Acme Global Enterprises', $this->orgA->fresh()->name);
        $this->assertEquals('admin@acmeglobal.com', $this->orgA->fresh()->contact_email);
    }

    // =======================================================
    // 2. UNLIMITED TELEGRAM-ONLY EMPLOYEES CRUD & LINKING
    // =======================================================

    public function test_can_create_unlimited_telegram_only_employees_without_consuming_seats(): void
    {
        // Fill 5 web seats
        for ($i = 1; $i <= 3; $i++) {
            User::factory()->create([
                'organization_id' => $this->orgA->id,
                'status' => UserStatus::ACTIVE,
            ]);
        }

        // Create 10 telegram employees in Org A
        for ($i = 1; $i <= 10; $i++) {
            $response = $this->actingAs($this->adminA)->post(route('admin.telegram-employees.store'), [
                'name' => "Field Worker {$i}",
                'employee_code' => "FW-10{$i}",
                'phone' => "+91 98765432{$i}",
                'department_id' => $this->deptA->id,
                'is_active' => 1,
            ]);
            $response->assertRedirect();
        }

        $this->assertEquals(10, TelegramEmployee::where('organization_id', $this->orgA->id)->count());

        // Web seats should still be exactly 5
        $seatService = app(OrganizationSeatService::class);
        $usage = $seatService->getSeatUsage($this->orgA);
        $this->assertEquals(5, $usage['active_seats']);
        $this->assertEquals(5, User::where('organization_id', $this->orgA->id)->where('status', UserStatus::ACTIVE)->count());
    }

    public function test_telegram_employee_has_no_web_user_account_or_password(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Driver Ramesh',
            'employee_code' => 'DRV-001',
            'phone' => '+91 9999988888',
            'is_active' => true,
        ]);

        // Attempting to log in as employee on web portal must fail
        $response = $this->post('/login', [
            'email' => 'drv-001@acme.com',
            'password' => 'password',
        ]);
        $this->assertGuest();
        $this->assertNull(User::where('name', 'Driver Ramesh')->first());
    }

    public function test_telegram_employee_linking_flow_with_verification_code(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Site Supervisor Suresh',
            'employee_code' => 'SUP-002',
            'is_active' => true,
        ]);

        // Admin generates linking code
        $response = $this->actingAs($this->adminA)->post(route('admin.telegram-employees.generate-code', $employee));
        $response->assertRedirect();

        $account = $employee->fresh()->telegramAccount;
        $this->assertNotNull($account);
        $this->assertStringStartsWith('TGEMP-', $account->verification_code);
        $this->assertFalse($account->isVerified());

        // Employee sends linking code to Telegram bot
        $updateService = app(TelegramUpdateService::class);
        $result = $updateService->handleIncomingText(
            chatId: '77889900',
            telegramUserId: '77889900',
            username: 'suresh_supervisor',
            text: $account->verification_code
        );

        $this->assertTrue($result['handled']);
        $this->assertTrue($account->fresh()->isVerified());
        $this->assertEquals('77889900', $account->fresh()->chat_id);
        $this->assertEquals('suresh_supervisor', $account->fresh()->username);
    }

    public function test_telegram_employee_linking_rejects_expired_or_invalid_code(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Technician Amit',
            'employee_code' => 'TECH-003',
            'is_active' => true,
        ]);

        $accountService = app(TelegramAccountService::class);
        $code = $accountService->generateCodeForTelegramEmployee($employee);

        // Manually expire code
        $account = $employee->fresh()->telegramAccount;
        $account->update(['verification_expires_at' => now()->subMinutes(5)]);

        $updateService = app(TelegramUpdateService::class);
        $result = $updateService->handleIncomingText(
            chatId: '11223344',
            telegramUserId: '11223344',
            username: 'amit_tech',
            text: $code
        );

        $this->assertTrue($result['handled']);
        $this->assertFalse($account->fresh()->isVerified());
    }

    // =======================================================
    // 3. TASK ASSIGNMENT & TELEGRAM COMPLETION
    // =======================================================

    public function test_task_assigned_to_telegram_employee_sends_telegram_message(): void
    {
        Http::fake([
            'api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 9911]], 200),
        ]);

        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Operator Vijay',
            'employee_code' => 'OP-101',
            'is_active' => true,
        ]);

        TelegramAccount::create([
            'telegram_employee_id' => $employee->id,
            'chat_id' => '55443322',
            'telegram_user_id' => '55443322',
            'username' => 'vijay_op',
            'verified_at' => now(),
            'is_active' => true,
        ]);

        $project = Project::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Site Alpha',
            'code' => 'PRJ-ALP',
            'created_by' => $this->adminA->id,
        ]);

        // Create Task assigned to Telegram Employee
        $response = $this->actingAs($this->adminA)->post(route('tasks.store'), [
            'code' => 'TSK-TG-001',
            'title' => 'Inspect water valves',
            'description' => 'Check main pressure at valve #3',
            'project_id' => $project->id,
            'assigned_telegram_employee_id' => $employee->id,
            'priority' => TaskPriority::HIGH->value,
            'status' => TaskStatus::PENDING->value,
            'due_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('tasks.index'));
        $task = Task::where('code', 'TSK-TG-001')->first();
        $this->assertNotNull($task);
        $this->assertEquals($employee->id, $task->assigned_telegram_employee_id);
        $this->assertEquals('Operator Vijay', $task->responsible_name);

        // Verify Telegram message was sent
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage') &&
                   str_contains($request['text'], 'TSK-TG-001') &&
                   str_contains($request['text'], 'Inspect water valves');
        });
    }

    public function test_telegram_employee_completes_task_via_telegram_command(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Operator Vijay',
            'employee_code' => 'OP-101',
            'is_active' => true,
        ]);

        TelegramAccount::create([
            'telegram_employee_id' => $employee->id,
            'chat_id' => '55443322',
            'telegram_user_id' => '55443322',
            'username' => 'vijay_op',
            'verified_at' => now(),
            'is_active' => true,
        ]);

        $project = Project::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Maintenance Project',
            'code' => 'PRJ-MNT',
            'created_by' => $this->adminA->id,
        ]);

        $task = Task::create([
            'organization_id' => $this->orgA->id,
            'project_id' => $project->id,
            'code' => 'TSK-DONE-99',
            'title' => 'Replace air filter',
            'assigned_telegram_employee_id' => $employee->id,
            'created_by' => $this->adminA->id,
            'priority' => TaskPriority::MEDIUM,
            'status' => TaskStatus::PENDING,
        ]);

        $updateService = app(TelegramUpdateService::class);
        $result = $updateService->handleIncomingText(
            chatId: '55443322',
            telegramUserId: '55443322',
            username: 'vijay_op',
            text: 'DONE TSK-DONE-99'
        );

        $this->assertTrue($result['handled']);
        $this->assertEquals(TaskStatus::COMPLETED, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);

        // Verify Audit Log records TelegramEmployee as actor
        $audit = AuditLog::where('auditable_type', Task::class)
            ->where('auditable_id', $task->id)
            ->where('action', AuditAction::COMPLETED->value)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals(TelegramEmployee::class, $audit->actor_type);
        $this->assertEquals($employee->id, $audit->actor_id);
    }

    // =======================================================
    // 4. PROCESS & CHECKLIST TELEGRAM INTERACTION
    // =======================================================

    public function test_process_assigned_to_telegram_employee_and_interactive_checklist_completion(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Field Engineer Vikas',
            'employee_code' => 'ENG-505',
            'department_id' => $this->deptA->id,
            'is_active' => true,
        ]);

        TelegramAccount::create([
            'telegram_employee_id' => $employee->id,
            'chat_id' => '66778899',
            'telegram_user_id' => '66778899',
            'username' => 'vikas_eng',
            'verified_at' => now(),
            'is_active' => true,
        ]);

        // Create Process assigned to Telegram Employee
        $process = Process::create([
            'organization_id' => $this->orgA->id,
            'department_id' => $this->deptA->id,
            'name' => 'Generator Daily Inspection',
            'code' => 'PRC-GEN-01',
            'responsible_telegram_employee_id' => $employee->id,
            'frequency' => ProcessFrequency::DAILY,
            'interval' => 1,
            'reminder_enabled' => true,
            'reminder_time' => '09:00',
            'next_run_at' => now()->startOfDay()->addHours(9),
            'status' => ProcessStatus::ACTIVE,
            'starts_at' => now()->subDay(),
        ]);

        // Add 3 Checklist Items with different response types
        $item1 = ProcessItem::create([
            'process_id' => $process->id,
            'question' => 'Fuel level checked above 50%?',
            'response_type' => ProcessResponseType::YES_NO,
            'sort_order' => 1,
            'is_required' => true,
            'is_enabled' => true,
        ]);

        $item2 = ProcessItem::create([
            'process_id' => $process->id,
            'question' => 'Current battery voltage',
            'response_type' => ProcessResponseType::NUMBER,
            'sort_order' => 2,
            'is_required' => true,
            'is_enabled' => true,
        ]);

        $item3 = ProcessItem::create([
            'process_id' => $process->id,
            'question' => 'Technician remarks',
            'response_type' => ProcessResponseType::TEXT,
            'sort_order' => 3,
            'is_required' => false,
            'is_enabled' => true,
        ]);

        // Generate Execution using ScheduleService
        $scheduleService = app(ProcessScheduleService::class);
        $execution = $scheduleService->generateExecutionForDate($process, now()->startOfDay()->addHours(9));

        $this->assertNotNull($execution);
        $this->assertEquals($this->orgA->id, $execution->organization_id);
        $this->assertEquals($employee->id, $execution->process->responsible_telegram_employee_id);
        $this->assertEquals('Field Engineer Vikas', $execution->responsible_name_snapshot);
        $this->assertEquals(ProcessExecutionStatus::PENDING, $execution->status);

        $updateService = app(TelegramUpdateService::class);

        // 1. Employee answers Question 1: YES
        $res1 = $updateService->handleIncomingText('66778899', '66778899', 'vikas_eng', 'YES');
        $this->assertTrue($res1['handled']);
        $eItem1 = ProcessExecutionItem::where('process_execution_id', $execution->id)->where('process_item_id', $item1->id)->first();
        $this->assertEquals('yes', $eItem1->response);
        $this->assertEquals($employee->id, $eItem1->answered_by_telegram_employee_id);

        // 2. Employee answers Question 2: NUM 24.5
        $res2 = $updateService->handleIncomingText('66778899', '66778899', 'vikas_eng', 'NUM 24.5');
        $this->assertTrue($res2['handled']);
        $eItem2 = ProcessExecutionItem::where('process_execution_id', $execution->id)->where('process_item_id', $item2->id)->first();
        $this->assertEquals('24.5', $eItem2->response);
        $this->assertEquals($employee->id, $eItem2->answered_by_telegram_employee_id);

        // 3. Employee answers Question 3: TEXT All normal and clean
        $res3 = $updateService->handleIncomingText('66778899', '66778899', 'vikas_eng', 'TEXT All normal and clean');
        $this->assertTrue($res3['handled']);
        $eItem3 = ProcessExecutionItem::where('process_execution_id', $execution->id)->where('process_item_id', $item3->id)->first();
        $this->assertEquals('All normal and clean', $eItem3->response);
        $this->assertEquals($employee->id, $eItem3->answered_by_telegram_employee_id);

        // 4. Employee completes checklist
        $resComplete = $updateService->handleIncomingText('66778899', '66778899', 'vikas_eng', 'COMPLETE');
        $this->assertTrue($resComplete['handled']);
        $this->assertEquals(ProcessExecutionStatus::COMPLETED, $execution->fresh()->status);
        $this->assertEquals($employee->id, $execution->fresh()->completed_by_telegram_employee_id);
        $this->assertNotNull($execution->fresh()->completed_at);

        // Audit log verified
        $audit = AuditLog::where('auditable_type', ProcessExecution::class)
            ->where('auditable_id', $execution->id)
            ->where('action', AuditAction::COMPLETED->value)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals(TelegramEmployee::class, $audit->actor_type);
        $this->assertEquals($employee->id, $audit->actor_id);
    }

    // =======================================================
    // 5. MULTI-TENANT CROSS-ORGANIZATION DATA ISOLATION & IDOR
    // =======================================================

    public function test_tenant_a_cannot_view_or_modify_tenant_b_resources(): void
    {
        // Setup Tenant B resources
        $projectB = Project::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Secret Project B',
            'code' => 'PRJ-B-01',
            'created_by' => $this->adminB->id,
        ]);

        $taskB = Task::create([
            'organization_id' => $this->orgB->id,
            'code' => 'TSK-B-001',
            'title' => 'Confidential Task B',
            'project_id' => $projectB->id,
            'created_by' => $this->adminB->id,
            'priority' => TaskPriority::HIGH,
            'status' => TaskStatus::PENDING,
        ]);

        $processB = Process::create([
            'organization_id' => $this->orgB->id,
            'department_id' => $this->deptB->id,
            'name' => 'Private Process B',
            'code' => 'PRC-B-001',
            'frequency' => ProcessFrequency::DAILY,
            'status' => ProcessStatus::ACTIVE,
            'starts_at' => now(),
        ]);

        $employeeB = TelegramEmployee::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Employee in Org B',
            'employee_code' => 'B-EMP-1',
            'is_active' => true,
        ]);

        // Admin A in Org A attempts to access Org B Project -> 403 / 404
        $this->actingAs($this->adminA)->get(route('projects.show', $projectB))->assertForbidden();

        // Admin A attempts to edit Org B Task -> 403
        $this->actingAs($this->adminA)->get(route('tasks.edit', $taskB))->assertForbidden();
        $this->actingAs($this->adminA)->put(route('tasks.update', $taskB), [
            'code' => $taskB->code,
            'title' => 'Hacked Task',
            'project_id' => $projectB->id,
            'priority' => TaskPriority::HIGH->value,
            'status' => TaskStatus::PENDING->value,
        ])->assertForbidden();

        // Admin A attempts to access Org B Process -> 403
        $this->actingAs($this->adminA)->get(route('processes.show', $processB))->assertForbidden();

        // Admin A attempts to access Org B Telegram Employee -> 403
        $this->actingAs($this->adminA)->get(route('admin.telegram-employees.show', $employeeB))->assertForbidden();
    }

    public function test_tenant_a_list_views_never_leak_tenant_b_records(): void
    {
        $projectB = Project::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Project B',
            'code' => 'PRJ-B-LEAK',
            'created_by' => $this->adminB->id,
        ]);

        $projectA = Project::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Project A',
            'code' => 'PRJ-A-SAFE',
            'created_by' => $this->adminA->id,
        ]);

        // Org B task
        Task::create([
            'organization_id' => $this->orgB->id,
            'project_id' => $projectB->id,
            'code' => 'TSK-B-LEAK',
            'title' => 'Secret Beta Task',
            'created_by' => $this->adminB->id,
            'priority' => TaskPriority::HIGH,
            'status' => TaskStatus::PENDING,
        ]);

        // Org A task
        Task::create([
            'organization_id' => $this->orgA->id,
            'project_id' => $projectA->id,
            'code' => 'TSK-A-SAFE',
            'title' => 'Normal Acme Task',
            'created_by' => $this->adminA->id,
            'priority' => TaskPriority::HIGH,
            'status' => TaskStatus::PENDING,
        ]);

        $response = $this->actingAs($this->adminA)->get(route('tasks.index'));
        $response->assertOk();
        $response->assertSee('TSK-A-SAFE');
        $response->assertDontSee('TSK-B-LEAK');
    }

    public function test_telegram_employee_toggle_active_and_unlink(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Operator Ganesh',
            'employee_code' => 'OP-GAN',
            'is_active' => true,
        ]);

        TelegramAccount::create([
            'telegram_employee_id' => $employee->id,
            'chat_id' => '12345678',
            'verified_at' => now(),
            'is_active' => true,
        ]);

        // Toggle active -> inactive
        $this->actingAs($this->adminA)->patch(route('admin.telegram-employees.toggle-active', $employee));
        $this->assertFalse($employee->fresh()->is_active);

        // Toggle inactive -> active
        $this->actingAs($this->adminA)->patch(route('admin.telegram-employees.toggle-active', $employee));
        $this->assertTrue($employee->fresh()->is_active);

        // Unlink account
        $this->actingAs($this->adminA)->post(route('admin.telegram-employees.unlink', $employee));
        $this->assertNull($employee->fresh()->telegramAccount->chat_id);
        $this->assertFalse($employee->fresh()->telegramAccount->is_active);
    }

    public function test_telegram_webhook_route_processes_payload(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Field Operator Deepak',
            'employee_code' => 'OP-DPK',
            'is_active' => true,
        ]);

        $account = TelegramAccount::create([
            'telegram_employee_id' => $employee->id,
            'verification_code' => 'TGEMP-DPK123',
            'verification_expires_at' => now()->addMinutes(15),
            'is_active' => false,
        ]);

        $payload = [
            'update_id' => 998877,
            'message' => [
                'message_id' => 5432,
                'from' => [
                    'id' => 88776655,
                    'is_bot' => false,
                    'first_name' => 'Deepak',
                    'username' => 'deepak_op',
                ],
                'chat' => [
                    'id' => 88776655,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'TGEMP-DPK123',
            ],
        ];

        config(['services.telegram.webhook_secret' => 'test-secret']);

        $response = $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
            ->postJson('/telegram/webhook', $payload);
        $response->assertOk();

        $this->assertTrue($account->fresh()->isVerified());
        $this->assertEquals('88776655', $account->fresh()->chat_id);
    }

    public function test_scheduler_processes_due_processes_across_multiple_organizations_correctly(): void
    {
        $procA = Process::create([
            'organization_id' => $this->orgA->id,
            'department_id' => $this->deptA->id,
            'name' => 'Daily Shift A',
            'code' => 'PRC-SHF-A',
            'responsible_user_id' => $this->adminA->id,
            'frequency' => ProcessFrequency::DAILY,
            'status' => ProcessStatus::ACTIVE,
            'next_run_at' => now()->subMinutes(10),
            'starts_at' => now()->subDay(),
        ]);

        $procB = Process::create([
            'organization_id' => $this->orgB->id,
            'department_id' => $this->deptB->id,
            'name' => 'Daily Shift B',
            'code' => 'PRC-SHF-B',
            'responsible_user_id' => $this->adminB->id,
            'frequency' => ProcessFrequency::DAILY,
            'status' => ProcessStatus::ACTIVE,
            'next_run_at' => now()->subMinutes(10),
            'starts_at' => now()->subDay(),
        ]);

        $scheduleService = app(ProcessScheduleService::class);
        $stats = $scheduleService->processDueProcesses();

        $this->assertEquals(2, $stats['generated']);
        $this->assertDatabaseHas('process_executions', ['process_id' => $procA->id, 'organization_id' => $this->orgA->id]);
        $this->assertDatabaseHas('process_executions', ['process_id' => $procB->id, 'organization_id' => $this->orgB->id]);
    }

    public function test_manager_can_manage_telegram_employees_in_same_organization(): void
    {
        $response = $this->actingAs($this->managerA)->get(route('admin.telegram-employees.index'));
        $response->assertOk();

        $createResponse = $this->actingAs($this->managerA)->post(route('admin.telegram-employees.store'), [
            'name' => 'Manager Added Worker',
            'department_id' => $this->deptA->id,
            'is_active' => 1,
        ]);
        $createResponse->assertRedirect();
        $this->assertDatabaseHas('telegram_employees', [
            'organization_id' => $this->orgA->id,
            'name' => 'Manager Added Worker',
        ]);
    }
}
