<?php

namespace Tests\Feature;

use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessFrequency;
use App\Enums\ProcessItemStatus;
use App\Enums\ProcessResponseType;
use App\Enums\ProcessStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Process;
use App\Models\ProcessExecution;
use App\Models\ProcessExecutionItem;
use App\Models\User;
use App\Services\ProcessAccountabilityService;
use App\Services\ProcessExecutionStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountabilityMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager;

    protected User $employee1;

    protected User $employee2;

    protected Department $department;

    protected Process $process1;

    protected Process $process2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $this->employee1 = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $this->employee2 = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        $this->department = Department::create([
            'name' => 'Finance & Accounts',
            'code' => 'FIN',
            'description' => 'Accounts and compliance department',
        ]);

        $this->process1 = Process::create([
            'code' => 'FIN-DLY-0001',
            'name' => 'Bank Transactions Reconciliation',
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee1->id,
            'frequency' => ProcessFrequency::DAILY,
            'preferred_time' => '17:00:00',
            'reminder_time' => '17:00:00',
            'status' => ProcessStatus::ACTIVE,
            'next_run_at' => now(),
        ]);

        $this->process1->items()->createMany([
            [
                'order' => 1,
                'question' => 'Were bank statements downloaded and matched?',
                'response_type' => ProcessResponseType::YES_NO,
                'is_required' => true,
            ],
            [
                'order' => 2,
                'question' => 'Are there any unposted items?',
                'response_type' => ProcessResponseType::YES_NO_NA,
                'is_required' => false,
            ],
        ]);

        $this->process2 = Process::create([
            'code' => 'FIN-DLY-0002',
            'name' => 'Cash Position Verification',
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee2->id,
            'frequency' => ProcessFrequency::DAILY,
            'preferred_time' => '18:00:00',
            'reminder_time' => '18:00:00',
            'status' => ProcessStatus::ACTIVE,
            'next_run_at' => now(),
        ]);
    }

    private function createExecution(Process $process, Carbon $scheduledFor, ProcessExecutionStatus $status = ProcessExecutionStatus::PENDING, ?User $completedBy = null): ProcessExecution
    {
        $execution = ProcessExecution::create([
            'process_id' => $process->id,
            'scheduled_for' => $scheduledFor,
            'status' => $status,
            'started_at' => $status !== ProcessExecutionStatus::PENDING ? $scheduledFor : null,
            'completed_at' => $status === ProcessExecutionStatus::COMPLETED ? $scheduledFor->copy()->addMinutes(15) : null,
            'completed_by' => $status === ProcessExecutionStatus::COMPLETED ? ($completedBy ? $completedBy->id : $process->responsible_user_id) : null,
            'occurrence_key' => $process->id.'-'.$scheduledFor->format('Y-m-d\TH:i:s'),
        ]);

        foreach ($process->items as $item) {
            ProcessExecutionItem::create([
                'process_execution_id' => $execution->id,
                'process_item_id' => $item->id,
                'question_snapshot' => $item->question,
                'response_type' => $item->response_type,
                'is_required' => $item->is_required,
                'response' => $status === ProcessExecutionStatus::COMPLETED ? 'YES' : null,
                'answered_by' => $status === ProcessExecutionStatus::COMPLETED ? $execution->completed_by : null,
                'answered_at' => $status === ProcessExecutionStatus::COMPLETED ? $execution->completed_at : null,
                'status' => $status === ProcessExecutionStatus::COMPLETED ? ProcessItemStatus::ANSWERED : ProcessItemStatus::PENDING,
            ]);
        }

        return $execution;
    }

    // 1. Authorization checks
    public function test_admin_and_manager_can_access_department_accountability(): void
    {
        $responseAdmin = $this->actingAs($this->admin)->get(route('accountability.departments.index'));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee('Department Accountability');
        $responseAdmin->assertSee('Finance & Accounts');

        $responseManager = $this->actingAs($this->manager)->get(route('accountability.departments.index'));
        $responseManager->assertOk();
    }

    public function test_employee_cannot_access_department_accountability_returns_forbidden(): void
    {
        $response = $this->actingAs($this->employee1)->get(route('accountability.departments.index'));
        $response->assertForbidden();

        $responseDetail = $this->actingAs($this->employee1)->get(route('accountability.departments.show', $this->department));
        $responseDetail->assertForbidden();
    }

    public function test_admin_and_manager_can_access_users_accountability(): void
    {
        $responseAdmin = $this->actingAs($this->admin)->get(route('accountability.users.index'));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee('People Accountability');
        $responseAdmin->assertSee($this->employee1->name);

        $responseManager = $this->actingAs($this->manager)->get(route('accountability.users.index'));
        $responseManager->assertOk();
    }

    public function test_employee_cannot_access_users_accountability_returns_forbidden(): void
    {
        $response = $this->actingAs($this->employee1)->get(route('accountability.users.index'));
        $response->assertForbidden();

        $responseDetail = $this->actingAs($this->employee1)->get(route('accountability.users.show', $this->employee2));
        $responseDetail->assertForbidden();
    }

    public function test_admin_and_manager_can_access_processes_accountability(): void
    {
        $responseAdmin = $this->actingAs($this->admin)->get(route('accountability.processes.index'));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee('Process Performance');
        $responseAdmin->assertSee('Bank Transactions Reconciliation');

        $responseManager = $this->actingAs($this->manager)->get(route('accountability.processes.index'));
        $responseManager->assertOk();
    }

    public function test_employee_cannot_access_processes_accountability_returns_forbidden(): void
    {
        $response = $this->actingAs($this->employee1)->get(route('accountability.processes.index'));
        $response->assertForbidden();

        $responseDetail = $this->actingAs($this->employee1)->get(route('accountability.processes.show', $this->process1));
        $responseDetail->assertForbidden();
    }

    // 2. Monitoring & Filtering
    public function test_process_monitoring_filters_by_status_completed(): void
    {
        $completedExec = $this->createExecution($this->process1, now()->subHour(), ProcessExecutionStatus::COMPLETED, $this->employee1);
        $pendingExec = $this->createExecution($this->process2, now()->addHour(), ProcessExecutionStatus::PENDING);

        $response = $this->actingAs($this->admin)->get(route('process-executions.index', ['status' => 'completed']));
        $response->assertOk();
        $response->assertSee($this->process1->code);
        $response->assertDontSee($this->process2->code);
        $this->assertTrue($response->viewData('executions')->contains('id', $completedExec->id));
        $this->assertFalse($response->viewData('executions')->contains('id', $pendingExec->id));
    }

    public function test_process_monitoring_filters_by_status_overdue(): void
    {
        // 2 hours past scheduled, status pending -> OVERDUE
        $overdueExec = $this->createExecution($this->process1, now()->subHours(2), ProcessExecutionStatus::PENDING);
        // Completed execution in the past is NOT overdue
        $completedExec = $this->createExecution($this->process1, now()->subHours(3), ProcessExecutionStatus::COMPLETED, $this->employee1);
        // Future execution is NOT overdue
        $futureExec = $this->createExecution($this->process2, now()->addHours(2), ProcessExecutionStatus::PENDING);

        $response = $this->actingAs($this->admin)->get(route('process-executions.index', ['status' => 'overdue']));
        $response->assertOk();
        $response->assertSee('Overdue');
        $response->assertSee($this->process1->code);
        $response->assertDontSee($this->process2->code);
        $this->assertTrue($response->viewData('executions')->contains('id', $overdueExec->id));
        $this->assertFalse($response->viewData('executions')->contains('id', $futureExec->id));
    }

    public function test_process_monitoring_filters_by_department(): void
    {
        $hrDept = Department::create(['name' => 'Human Resources', 'code' => 'HR']);
        $hrProcess = Process::create([
            'code' => 'HR-DLY-0001',
            'name' => 'Daily Attendance Check',
            'department_id' => $hrDept->id,
            'responsible_user_id' => $this->employee2->id,
            'frequency' => ProcessFrequency::DAILY,
            'status' => ProcessStatus::ACTIVE,
            'next_run_at' => now(),
        ]);

        $finExec = $this->createExecution($this->process1, now());
        $hrExec = $this->createExecution($hrProcess, now());

        $response = $this->actingAs($this->admin)->get(route('process-executions.index', ['department_id' => $this->department->id]));
        $response->assertOk();
        $response->assertSee($this->process1->code);
        $response->assertDontSee($hrProcess->code);
        $this->assertTrue($response->viewData('executions')->contains('id', $finExec->id));
        $this->assertFalse($response->viewData('executions')->contains('id', $hrExec->id));
    }

    public function test_process_monitoring_filters_by_responsible_user(): void
    {
        $exec1 = $this->createExecution($this->process1, now()); // assigned to employee1
        $exec2 = $this->createExecution($this->process2, now()); // assigned to employee2

        $response = $this->actingAs($this->admin)->get(route('process-executions.index', ['responsible_user_id' => $this->employee1->id]));
        $response->assertOk();
        $response->assertSee($this->process1->code);
        $response->assertDontSee($this->process2->code);
        $this->assertTrue($response->viewData('executions')->contains('id', $exec1->id));
        $this->assertFalse($response->viewData('executions')->contains('id', $exec2->id));
    }

    public function test_employee_monitoring_automatically_scoped_to_own_executions(): void
    {
        $exec1 = $this->createExecution($this->process1, now()); // assigned to employee1
        $exec2 = $this->createExecution($this->process2, now()); // assigned to employee2

        $response = $this->actingAs($this->employee1)->get(route('process-executions.index'));
        $response->assertOk();
        $response->assertSee($this->process1->code);
        $response->assertDontSee($this->process2->code);
        $this->assertTrue($response->viewData('executions')->contains('id', $exec1->id));
        $this->assertFalse($response->viewData('executions')->contains('id', $exec2->id));
    }

    // 3. Overdue & Missed Logic
    public function test_overdue_logic_completed_execution_is_never_overdue(): void
    {
        $exec = $this->createExecution($this->process1, now()->subDay(), ProcessExecutionStatus::COMPLETED, $this->employee1);

        $this->assertFalse($exec->isOverdue());
    }

    public function test_overdue_logic_pending_past_due_is_overdue(): void
    {
        $exec = $this->createExecution($this->process1, now()->subHour(), ProcessExecutionStatus::PENDING);

        $this->assertTrue($exec->isOverdue());
    }

    public function test_overdue_logic_future_pending_is_not_overdue(): void
    {
        $exec = $this->createExecution($this->process1, now()->addHour(), ProcessExecutionStatus::PENDING);

        $this->assertFalse($exec->isOverdue());
    }

    public function test_missed_status_transition_via_service_and_artisan_command(): void
    {
        // Scheduled yesterday and uncompleted -> should transition to MISSED
        $oldExec = $this->createExecution($this->process1, Carbon::yesterday()->setHour(17), ProcessExecutionStatus::PENDING);
        // Scheduled today -> should remain PENDING (not marked missed yet)
        $todayExec = $this->createExecution($this->process2, today()->setHour(18), ProcessExecutionStatus::PENDING);

        $this->artisan('processes:status')
            ->expectsOutputToContain('1 executions marked as missed')
            ->assertExitCode(0);

        $oldExec->refresh();
        $todayExec->refresh();

        $this->assertEquals(ProcessExecutionStatus::MISSED, $oldExec->status);
        $this->assertEquals(ProcessExecutionStatus::PENDING, $todayExec->status);
    }

    public function test_processes_status_command_is_idempotent(): void
    {
        $oldExec = $this->createExecution($this->process1, Carbon::yesterday()->setHour(17), ProcessExecutionStatus::PENDING);

        $statusService = app(ProcessExecutionStatusService::class);
        $firstRun = $statusService->markMissedExecutions();
        $this->assertEquals(1, $firstRun);

        $secondRun = $statusService->markMissedExecutions();
        $this->assertEquals(0, $secondRun);
    }

    // 4. Date Range Filtering & Accountability Metrics
    public function test_date_range_resolution_and_filtering(): void
    {
        $service = app(ProcessAccountabilityService::class);

        $todayRange = $service->resolveDateRange('today');
        $this->assertEquals(today()->startOfDay()->toDateTimeString(), $todayRange['start']->toDateTimeString());
        $this->assertEquals(today()->endOfDay()->toDateTimeString(), $todayRange['end']->toDateTimeString());

        $thisMonthRange = $service->resolveDateRange('this_month');
        $this->assertEquals(now()->startOfMonth()->toDateTimeString(), $thisMonthRange['start']->toDateTimeString());

        $customRange = $service->resolveDateRange('custom', '2026-09-01', '2026-09-15');
        $this->assertEquals('2026-09-01 00:00:00', $customRange['start']->toDateTimeString());
        $this->assertEquals('2026-09-15 23:59:59', $customRange['end']->toDateTimeString());
    }

    public function test_department_statistics_calculation_and_completion_percentage(): void
    {
        // Create 2 executions for this month: 1 completed, 1 pending
        $this->createExecution($this->process1, now()->startOfMonth()->addDays(2), ProcessExecutionStatus::COMPLETED, $this->employee1);
        $this->createExecution($this->process2, now()->startOfMonth()->addDays(3), ProcessExecutionStatus::PENDING);

        $service = app(ProcessAccountabilityService::class);
        $stats = $service->getDepartmentStatistics('this_month');

        $this->assertEquals(2, $stats['summary']['total']);
        $this->assertEquals(1, $stats['summary']['completed']);
        $this->assertEquals(50.0, $stats['summary']['completion_rate']);

        $deptItem = collect($stats['departments'])->firstWhere('id', $this->department->id);
        $this->assertNotNull($deptItem);
        $this->assertEquals(2, $deptItem['metrics']['total']);
        $this->assertEquals(1, $deptItem['metrics']['completed']);
        $this->assertEquals(50.0, $deptItem['metrics']['completion_rate']);
    }

    public function test_user_statistics_calculation_and_factual_presentation(): void
    {
        $this->createExecution($this->process1, now()->startOfMonth()->addDays(2), ProcessExecutionStatus::COMPLETED, $this->employee1);

        $service = app(ProcessAccountabilityService::class);
        $stats = $service->getUserStatistics('this_month');

        $userItem = collect($stats['users'])->firstWhere('id', $this->employee1->id);
        $this->assertNotNull($userItem);
        $this->assertEquals(1, $userItem['metrics']['total']);
        $this->assertEquals(1, $userItem['metrics']['completed']);
        $this->assertEquals(100.0, $userItem['metrics']['completion_rate']);
    }

    public function test_process_statistics_calculation(): void
    {
        $this->createExecution($this->process1, now()->startOfMonth()->addDays(2), ProcessExecutionStatus::COMPLETED, $this->employee1);

        $service = app(ProcessAccountabilityService::class);
        $stats = $service->getProcessStatistics('this_month');

        $procItem = collect($stats['processes'])->firstWhere('id', $this->process1->id);
        $this->assertNotNull($procItem);
        $this->assertEquals(1, $procItem['metrics']['total']);
        $this->assertEquals(1, $procItem['metrics']['completed']);
        $this->assertEquals(100.0, $procItem['metrics']['completion_rate']);
    }

    public function test_zero_execution_handles_zero_division_safely(): void
    {
        $emptyDept = Department::create(['name' => 'Legal & Compliance', 'code' => 'LEG']);
        $service = app(ProcessAccountabilityService::class);

        $detail = $service->getDepartmentDetail($emptyDept, 'this_month');

        $this->assertEquals(0, $detail['metrics']['total']);
        $this->assertEquals(0, $detail['metrics']['completed']);
        $this->assertEquals(0.0, $detail['metrics']['completion_rate']);
    }

    // 5. Dashboard & Confirmation display
    public function test_dashboard_displays_today_process_accountability_metrics(): void
    {
        $this->createExecution($this->process1, today()->setHour(10), ProcessExecutionStatus::COMPLETED, $this->employee1);
        $this->createExecution($this->process2, today()->setHour(14), ProcessExecutionStatus::PENDING);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Process Accountability');
        $response->assertSee('Bank Transactions Reconciliation');
        $response->assertSee('Completed');
    }

    public function test_human_confirmation_display_in_execution_detail_view(): void
    {
        $completedExec = $this->createExecution($this->process1, now()->subHours(2), ProcessExecutionStatus::COMPLETED, $this->employee1);

        $response = $this->actingAs($this->admin)->get(route('process-executions.show', $completedExec));
        $response->assertOk();
        $response->assertSee('Checklist Confirmed');
        $response->assertSee('Confirmed by');
        $response->assertSee($this->employee1->name);
        $response->assertSee('Were bank statements downloaded and matched?');
        $response->assertSee('Yes');
    }
}
