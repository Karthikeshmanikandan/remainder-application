<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ProcessEscalationStatus;
use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessFrequency;
use App\Enums\ProcessResponseType;
use App\Enums\ProcessStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\NotificationPreference;
use App\Models\Process;
use App\Models\ProcessEscalationEvent;
use App\Models\ProcessExecution;
use App\Models\ProcessTemplate;
use App\Models\ProcessTemplateItem;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Notifications\ProcessEscalationNotification;
use App\Services\AuditLogService;
use App\Services\ProcessEscalationService;
use App\Services\ProcessExecutionService;
use App\Services\ProcessExecutionStatusService;
use App\Services\ProcessReportingService;
use App\Services\ProcessScheduleService;
use App\Services\ProcessService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProductionIntegrityEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager;

    protected User $employeeA;

    protected User $employeeB;

    protected ProcessService $processService;

    protected ProcessScheduleService $scheduleService;

    protected ProcessExecutionService $executionService;

    protected ProcessEscalationService $escalationService;

    protected ProcessExecutionStatusService $statusService;

    protected ProcessReportingService $reportingService;

    protected AuditLogService $auditLogService;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 15, 9, 0, 0));

        $this->admin = User::factory()->create(['name' => 'System Admin', 'role' => UserRole::ADMIN]);
        $this->manager = User::factory()->create(['name' => 'Manager A', 'role' => UserRole::MANAGER]);
        $this->employeeA = User::factory()->create(['name' => 'Employee A', 'role' => UserRole::EMPLOYEE]);
        $this->employeeB = User::factory()->create(['name' => 'Employee B', 'role' => UserRole::EMPLOYEE]);

        $this->processService = app(ProcessService::class);
        $this->scheduleService = app(ProcessScheduleService::class);
        $this->executionService = app(ProcessExecutionService::class);
        $this->escalationService = app(ProcessEscalationService::class);
        $this->statusService = app(ProcessExecutionStatusService::class);
        $this->reportingService = app(ProcessReportingService::class);
        $this->auditLogService = app(AuditLogService::class);
    }

    // =========================================================================
    // 1. COMPLETE REALISTIC END-TO-END BUSINESS PROCESS WORKFLOW
    // =========================================================================

    public function test_complete_business_flow_from_department_to_completion_audit_and_reporting(): void
    {
        // 1. Admin creates Department
        $deptResponse = $this->actingAs($this->admin)->post(route('admin.departments.store'), [
            'name' => 'Finance & Accounts',
            'code' => 'FIN',
            'description' => 'Handles corporate ledger and treasury',
            'is_active' => '1',
        ]);
        $department = Department::where('code', 'FIN')->first();
        $this->assertNotNull($department);
        $deptResponse->assertRedirect(route('admin.departments.show', $department));

        // 2. Admin creates Process Template Blueprint with Questions
        $tplResponse = $this->actingAs($this->admin)->post(route('admin.process-templates.store'), [
            'department_id' => $department->id,
            'name' => 'Daily Finance Checklist',
            'code' => 'FIN-DFC',
            'description' => 'Standard opening procedures for treasury',
            'frequency_default' => ProcessFrequency::DAILY->value,
            'is_active' => '1',
            'items' => [
                ['question' => 'Bank transactions updated?', 'response_type' => ProcessResponseType::YES_NO->value, 'is_required' => '1', 'sort_order' => '1'],
                ['question' => 'Receivables reviewed?', 'response_type' => ProcessResponseType::YES_NO->value, 'is_required' => '1', 'sort_order' => '2'],
                ['question' => 'Payables reviewed?', 'response_type' => ProcessResponseType::YES_NO->value, 'is_required' => '1', 'sort_order' => '3'],
                ['question' => 'Pending invoices checked?', 'response_type' => ProcessResponseType::NUMBER->value, 'is_required' => '1', 'sort_order' => '4'],
            ],
        ]);
        $template = ProcessTemplate::where('code', 'FIN-DFC')->first();
        $this->assertNotNull($template);
        $this->assertEquals(4, $template->items()->count());

        // 3. Process is created from the template and assigned to Employee A with escalation
        $procResponse = $this->actingAs($this->admin)->post(route('processes.store'), [
            'department_id' => $department->id,
            'process_template_id' => $template->id,
            'name' => 'Finance Daily Operations',
            'responsible_user_id' => $this->employeeA->id,
            'frequency' => ProcessFrequency::DAILY->value,
            'interval' => 1,
            'reminder_enabled' => 1,
            'reminder_time' => '09:00',
            'escalation_rules' => [
                [
                    'level' => 1,
                    'delay_minutes' => 30,
                    'escalate_to_user_id' => $this->manager->id,
                    'is_active' => 1,
                ],
            ],
        ]);
        $process = Process::where('name', 'Finance Daily Operations')->first();
        $this->assertNotNull($process);
        $this->assertEquals(4, $process->items()->count());
        $this->assertEquals(1, $process->escalationRules()->count());

        // 4. Verify audit log was created for process creation
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::CREATED->value,
            'user_id' => $this->admin->id,
        ]);

        // 5. Scheduler runs and creates execution with snapshot questions
        $this->artisan('processes:process')->assertExitCode(0);
        $execution = ProcessExecution::where('process_id', $process->id)->first();
        $this->assertNotNull($execution);
        $this->assertEquals(ProcessExecutionStatus::PENDING, $execution->status);
        $this->assertEquals(4, $execution->items()->count());
        $this->assertEquals('Bank transactions updated?', $execution->items[0]->question_snapshot);

        // 6. Employee A opens checklist (verifying access and questions)
        $checklistView = $this->actingAs($this->employeeA)->get(route('process-executions.show', $execution));
        $checklistView->assertStatus(200);
        $checklistView->assertSee('Bank transactions updated?');
        $checklistView->assertSee('Pending invoices checked?');

        // 7. Employee A confirms all answers
        $items = $execution->items;
        $answers = [
            $items[0]->id => ['response' => 'yes', 'notes' => 'All bank feeds reconciled at 09:15'],
            $items[1]->id => ['response' => 'yes', 'notes' => 'Customer receipts posted'],
            $items[2]->id => ['response' => 'yes', 'notes' => 'Vendor bills cleared for payment'],
            $items[3]->id => ['response' => '5', 'notes' => '5 invoices awaiting manager signoff'],
        ];

        $confirmResponse = $this->actingAs($this->employeeA)->post(route('process-executions.confirm', $execution), [
            'answers' => $answers,
        ]);
        $confirmResponse->assertRedirect(route('process-executions.show', $execution));

        // 8. Verify completion state and audit entry
        $execution->refresh();
        $this->assertEquals(ProcessExecutionStatus::COMPLETED, $execution->status);
        $this->assertEquals($this->employeeA->id, $execution->completed_by);
        $this->assertNotNull($execution->completed_at);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ProcessExecution::class,
            'auditable_id' => $execution->id,
            'action' => AuditAction::COMPLETED->value,
            'user_id' => $this->employeeA->id,
        ]);

        // 9. Advance time past the escalation threshold and run escalation processor
        Carbon::setTestNow(Carbon::now()->addMinutes(45));
        $this->artisan('processes:escalate')->assertExitCode(0);

        // Verify NO escalation event is triggered for a completed execution
        $this->assertEquals(0, ProcessEscalationEvent::where('process_execution_id', $execution->id)->count());

        // 10. Verify Business Intelligence / Reporting includes the execution
        $period = $this->reportingService->resolvePeriod('today');
        $deptReport = $this->reportingService->getDepartmentDetailReport($department, $period['start'], $period['end']);
        $this->assertEquals(1, $deptReport['metrics']['scheduled']);
        $this->assertEquals(1, $deptReport['metrics']['completed']);
        $this->assertEquals(100.0, $deptReport['metrics']['completion_rate']);

        $procReport = $this->reportingService->getProcessDetailReport($process, $period['start'], $period['end'], 'today');
        $this->assertEquals(1, $procReport['metrics']['scheduled']);
        $this->assertEquals(1, $procReport['metrics']['completed']);
    }

    // =========================================================================
    // 2. OVERDUE -> ESCALATION -> NOTIFICATION -> ACKNOWLEDGEMENT -> RESOLUTION FLOW
    // =========================================================================

    public function test_overdue_escalation_flow_notifications_idempotency_and_resolution(): void
    {
        Notification::fake();

        // 1. Create process due at 09:00 with 30m escalation to Manager
        $department = Department::create(['name' => 'Support', 'code' => 'SUP']);
        $process = $this->processService->createProcess([
            'department_id' => $department->id,
            'name' => 'Critical Ticket SLA Check',
            'responsible_user_id' => $this->employeeA->id,
            'frequency' => ProcessFrequency::DAILY->value,
            'interval' => 1,
            'reminder_time' => '09:00',
            'telegram_enabled' => true,
            'in_app_enabled' => true,
        ], [
            ['question' => 'Are all P1 tickets acknowledged?', 'response_type' => ProcessResponseType::YES_NO->value, 'is_required' => true, 'sort_order' => 1],
        ], [
            ['level' => 1, 'delay_minutes' => 30, 'escalate_to_user_id' => $this->manager->id, 'is_active' => true],
        ]);

        // 2. Generate scheduled execution for 09:00
        $this->artisan('processes:process')->assertExitCode(0);
        $execution = ProcessExecution::where('process_id', $process->id)->first();
        $this->assertNotNull($execution);
        $this->assertEquals(ProcessExecutionStatus::PENDING, $execution->status);

        // 3. Time advances by 10 minutes (09:10) -> Status is overdue, but not yet escalated
        Carbon::setTestNow(Carbon::create(2026, 10, 15, 9, 10, 0));
        $this->artisan('processes:status')->assertExitCode(0);
        $this->assertTrue($execution->fresh()->isOverdue());

        $this->artisan('processes:escalate')->assertExitCode(0);
        $this->assertEquals(0, ProcessEscalationEvent::count());

        // 4. Time advances by 35 minutes from scheduled time (09:35) -> Escalation threshold passed
        Carbon::setTestNow(Carbon::create(2026, 10, 15, 9, 35, 0));
        $this->artisan('processes:escalate')->assertExitCode(0);

        // Verify Escalation Event created
        $this->assertEquals(1, ProcessEscalationEvent::count());
        $event = ProcessEscalationEvent::first();
        $this->assertEquals($execution->id, $event->process_execution_id);
        $this->assertEquals(1, $event->level);
        $this->assertEquals(ProcessEscalationStatus::TRIGGERED, $event->status);

        // Verify notification sent to Manager
        Notification::assertSentTo($this->manager, ProcessEscalationNotification::class);

        // 5. Idempotency test: Running escalation command again does NOT create duplicate events
        $this->artisan('processes:escalate')->assertExitCode(0);
        $this->assertEquals(1, ProcessEscalationEvent::count());

        // 6. Manager acknowledges escalation
        $this->actingAs($this->manager)->post(route('accountability.escalations.acknowledge', $event));
        $event->refresh();
        $this->assertEquals(ProcessEscalationStatus::ACKNOWLEDGED, $event->status);
        $this->assertEquals($this->manager->id, $event->acknowledged_by);
        $this->assertNotNull($event->acknowledged_at);

        // Verify acknowledgement was audited
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ProcessEscalationEvent::class,
            'auditable_id' => $event->id,
            'action' => AuditAction::ACKNOWLEDGED->value,
            'user_id' => $this->manager->id,
        ]);

        // 7. Employee completes execution later -> Escalation automatically resolves
        $item = $execution->items->first();
        $this->actingAs($this->employeeA)->post(route('process-executions.confirm', $execution), [
            'answers' => [
                $item->id => ['response' => 'yes', 'notes' => 'Resolved delayed P1 ticket.'],
            ],
        ]);

        $execution->refresh();
        $event->refresh();
        $this->assertEquals(ProcessExecutionStatus::COMPLETED, $execution->status);
        $this->assertEquals(ProcessEscalationStatus::RESOLVED, $event->status);
    }

    // =========================================================================
    // 3. HISTORICAL CONFIGURATION IMMUTABILITY GUARANTEE
    // =========================================================================

    public function test_historical_execution_snapshots_remain_immutable_when_process_is_reconfigured(): void
    {
        $department = Department::create(['name' => 'Compliance', 'code' => 'CMP']);
        $process = $this->processService->createProcess([
            'department_id' => $department->id,
            'name' => 'AML Compliance Audit',
            'responsible_user_id' => $this->employeeA->id,
            'frequency' => ProcessFrequency::DAILY->value,
            'interval' => 1,
            'reminder_time' => '09:00',
        ], [
            ['question' => 'Bank transactions updated?', 'response_type' => ProcessResponseType::YES_NO->value, 'is_required' => true, 'sort_order' => 1],
        ]);

        // Run scheduler for Execution 1
        $this->artisan('processes:process')->assertExitCode(0);
        $exec1 = ProcessExecution::where('process_id', $process->id)->first();
        $item1 = $exec1->items->first();
        $this->assertEquals('Bank transactions updated?', $item1->question_snapshot);

        // Employee completes Execution 1
        $this->actingAs($this->employeeA)->post(route('process-executions.confirm', $exec1), [
            'answers' => [
                $item1->id => ['response' => 'yes', 'notes' => 'Execution 1 confirmation notes'],
            ],
        ]);
        $this->assertEquals(ProcessExecutionStatus::COMPLETED, $exec1->fresh()->status);

        // Now Admin updates Process Item question
        $processItem = $process->items->first();
        $processItem->update(['question' => 'Bank transactions updated and reconciled with treasury?']);

        // And Admin reassigns process to Employee B and changes reminder time to 17:00
        $this->actingAs($this->admin);
        $this->processService->updateProcess($process, [
            'responsible_user_id' => $this->employeeB->id,
            'reminder_time' => '17:00',
        ]);

        // Advance to next day and generate Execution 2
        Carbon::setTestNow(Carbon::create(2026, 10, 16, 17, 0, 0));
        $this->artisan('processes:process')->assertExitCode(0);

        $exec2 = ProcessExecution::where('process_id', $process->id)->where('id', '!=', $exec1->id)->first();
        $this->assertNotNull($exec2);
        $item2 = $exec2->items->first();

        // 1. Verify OLD execution remained completely unchanged
        $item1->refresh();
        $exec1->refresh();
        $this->assertEquals('Bank transactions updated?', $item1->question_snapshot);
        $this->assertEquals('yes', $item1->response);
        $this->assertEquals('Execution 1 confirmation notes', $item1->notes);
        $this->assertEquals($this->employeeA->id, $exec1->completed_by);

        // 2. Verify NEW execution uses updated question snapshot and target time
        $this->assertEquals('Bank transactions updated and reconciled with treasury?', $item2->question_snapshot);
        $this->assertEquals('2026-10-16 09:00:00', $exec2->scheduled_for->format('Y-m-d H:i:s'));
    }

    // =========================================================================
    // 4. TEMPLATE GOVERNANCE & PROCESS ISOLATION
    // =========================================================================

    public function test_modifying_template_does_not_mutate_existing_processes(): void
    {
        $department = Department::create(['name' => 'Store', 'code' => 'STR']);
        $template = ProcessTemplate::create([
            'department_id' => $department->id,
            'name' => 'Store Opening Blueprint',
            'code' => 'STR-SOB',
            'frequency_default' => ProcessFrequency::DAILY,
        ]);
        ProcessTemplateItem::create([
            'process_template_id' => $template->id,
            'question' => 'Check physical lock integrity',
            'response_type' => ProcessResponseType::YES_NO,
            'sort_order' => 1,
            'is_required' => true,
            'is_active' => true,
        ]);

        // Create Process 1 from Template
        $process1 = $this->processService->createProcess([
            'department_id' => $department->id,
            'process_template_id' => $template->id,
            'name' => 'Branch 1 Opening',
            'responsible_user_id' => $this->employeeA->id,
            'frequency' => ProcessFrequency::DAILY->value,
        ]);
        $this->assertEquals('Check physical lock integrity', $process1->items->first()->question);

        // Modify Template Question
        $template->items->first()->update(['question' => 'Check biometric & physical lock integrity']);

        // Create Process 2 from modified Template
        $process2 = $this->processService->createProcess([
            'department_id' => $department->id,
            'process_template_id' => $template->id,
            'name' => 'Branch 2 Opening',
            'responsible_user_id' => $this->employeeB->id,
            'frequency' => ProcessFrequency::DAILY->value,
        ]);

        // Verify Process 1 was NOT altered
        $process1->refresh();
        $this->assertEquals('Check physical lock integrity', $process1->items->first()->question);

        // Verify Process 2 has the updated question
        $this->assertEquals('Check biometric & physical lock integrity', $process2->items->first()->question);
    }

    // =========================================================================
    // 5. PROCESS LIFECYCLE: ACTIVE -> PAUSED -> RESUMED -> CANCELLED
    // =========================================================================

    public function test_process_lifecycle_scheduling_and_audit_consistency(): void
    {
        $department = Department::create(['name' => 'Warehouse', 'code' => 'WHS']);
        $process = $this->processService->createProcess([
            'department_id' => $department->id,
            'name' => 'Stock Tally Process',
            'responsible_user_id' => $this->employeeA->id,
            'frequency' => ProcessFrequency::DAILY->value,
            'reminder_time' => '09:00',
        ]);

        // 1. ACTIVE: generates execution
        $this->artisan('processes:process')->assertExitCode(0);
        $this->assertEquals(1, ProcessExecution::where('process_id', $process->id)->count());

        // 2. PAUSE: does not generate executions
        $this->actingAs($this->admin)->patch(route('processes.pause', $process));
        $this->assertEquals(ProcessStatus::PAUSED, $process->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::PAUSED->value,
        ]);

        Carbon::setTestNow(Carbon::now()->addDay());
        $this->artisan('processes:process')->assertExitCode(0);
        $this->assertEquals(1, ProcessExecution::where('process_id', $process->id)->count());

        // 3. RESUME: scheduling works again
        $this->actingAs($this->admin)->patch(route('processes.resume', $process));
        $this->assertEquals(ProcessStatus::ACTIVE, $process->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::RESUMED->value,
        ]);

        $this->artisan('processes:process')->assertExitCode(0);
        $this->assertEquals(2, ProcessExecution::where('process_id', $process->id)->count());

        // 4. CANCEL: permanently stops scheduling
        $this->actingAs($this->admin)->patch(route('processes.cancel', $process));
        $this->assertEquals(ProcessStatus::CANCELLED, $process->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::CANCELLED->value,
        ]);

        Carbon::setTestNow(Carbon::now()->addDay());
        $this->artisan('processes:process')->assertExitCode(0);
        $this->assertEquals(2, ProcessExecution::where('process_id', $process->id)->count());
    }

    // =========================================================================
    // 6. RBAC & IDOR ATTACK SURFACE TESTING
    // =========================================================================

    public function test_rbac_boundary_and_direct_url_attack_surfaces(): void
    {
        $department = Department::create(['name' => 'Legal', 'code' => 'LGL']);
        $template = ProcessTemplate::create(['department_id' => $department->id, 'name' => 'Legal Tpl', 'code' => 'LGL-TPL']);
        $auditLog = AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::CREATED, 'auditable_type' => Department::class, 'auditable_id' => $department->id, 'summary' => 'Initial']);

        // Employee attempting direct access to Admin / Governance / Audit URLs -> 403
        $this->actingAs($this->employeeA)->get('/admin/departments')->assertStatus(403);
        $this->actingAs($this->employeeA)->get('/admin/departments/create')->assertStatus(403);
        $this->actingAs($this->employeeA)->get('/admin/process-templates')->assertStatus(403);
        $this->actingAs($this->employeeA)->get('/admin/audit-logs')->assertStatus(403);
        $this->actingAs($this->employeeA)->get('/admin/audit-logs/'.$auditLog->id)->assertStatus(403);
        $this->actingAs($this->employeeA)->get('/accountability/departments')->assertStatus(403);
        $this->actingAs($this->employeeA)->get('/accountability/users')->assertStatus(403);
        $this->actingAs($this->employeeA)->get('/accountability/processes')->assertStatus(403);
        $this->actingAs($this->employeeA)->get('/accountability/escalations')->assertStatus(403);
        $this->actingAs($this->employeeA)->get('/reports')->assertStatus(403);

        // Employee mutation attempts -> 403
        $this->actingAs($this->employeeA)->post(route('admin.departments.store'), ['name' => 'Hack Dept', 'code' => 'HACK'])->assertStatus(403);
        $this->actingAs($this->employeeA)->delete(route('admin.departments.destroy', $department))->assertStatus(403);
        $this->actingAs($this->employeeA)->post(route('admin.process-templates.store'), ['name' => 'Hack Tpl'])->assertStatus(403);
    }

    public function test_idor_protection_between_unassigned_employees(): void
    {
        $department = Department::create(['name' => 'IT', 'code' => 'IT']);
        $processB = $this->processService->createProcess([
            'department_id' => $department->id,
            'name' => 'Employee B Private Task',
            'responsible_user_id' => $this->employeeB->id,
            'frequency' => ProcessFrequency::DAILY->value,
            'reminder_time' => '09:00',
        ], [
            ['question' => 'Sensitive Check?', 'response_type' => ProcessResponseType::YES_NO->value, 'is_required' => true, 'sort_order' => 1],
        ]);

        $this->artisan('processes:process')->assertExitCode(0);
        $executionB = ProcessExecution::where('process_id', $processB->id)->first();
        $this->assertNotNull($executionB);

        // Employee A attempting to view Employee B's execution -> 403
        $this->actingAs($this->employeeA)->get(route('process-executions.show', $executionB))->assertStatus(403);

        // Employee A attempting to submit progress or confirm Employee B's execution -> 403
        $itemB = $executionB->items->first();
        $this->actingAs($this->employeeA)->post(route('process-executions.save-progress', $executionB), [
            'answers' => [$itemB->id => ['response' => 'yes']],
        ])->assertStatus(403);

        $this->actingAs($this->employeeA)->post(route('process-executions.confirm', $executionB), [
            'answers' => [$itemB->id => ['response' => 'yes']],
        ])->assertStatus(403);
    }

    // =========================================================================
    // 7. TELEGRAM FAILURE-SAFETY & SENSITIVE CREDENTIAL SCRUBBING
    // =========================================================================

    public function test_telegram_failure_does_not_crash_system_or_leak_credentials(): void
    {
        // Fake Telegram failure (API returns 500 or network timeout)
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Internal Server Error'], 500),
        ]);

        $department = Department::create(['name' => 'Dispatch', 'code' => 'DSP']);
        $process = $this->processService->createProcess([
            'department_id' => $department->id,
            'name' => 'Telegram Monitored Delivery',
            'responsible_user_id' => $this->employeeA->id,
            'frequency' => ProcessFrequency::DAILY->value,
            'reminder_time' => '09:00',
            'telegram_enabled' => true,
            'in_app_enabled' => true,
        ], [
            ['question' => 'Vehicle inspected?', 'response_type' => ProcessResponseType::YES_NO->value, 'is_required' => true, 'sort_order' => 1],
        ], [
            ['level' => 1, 'delay_minutes' => 30, 'escalate_to_user_id' => $this->manager->id, 'is_active' => true],
        ]);

        // Link verified Telegram account to Manager
        TelegramAccount::create([
            'user_id' => $this->manager->id,
            'telegram_user_id' => '123456789',
            'chat_id' => '123456789',
            'is_active' => true,
            'verified_at' => now(),
        ]);
        NotificationPreference::create([
            'user_id' => $this->manager->id,
            'telegram_enabled' => true,
            'in_app_enabled' => true,
        ]);

        // 1. Generate execution -> doesn't crash despite Telegram network error
        $this->artisan('processes:process')->assertExitCode(0);
        $execution = ProcessExecution::where('process_id', $process->id)->first();
        $this->assertNotNull($execution);

        // 2. Advance time and run escalation -> logs failure safely without exception
        Carbon::setTestNow(Carbon::now()->addMinutes(45));
        $this->artisan('processes:escalate')->assertExitCode(0);

        // Verify escalation state succeeded despite Telegram failure
        $event = ProcessEscalationEvent::where('process_execution_id', $execution->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals(ProcessEscalationStatus::TRIGGERED, $event->status);

        // 3. Verify audit log sanitization completely scrubbed any potential tokens
        $log = $this->auditLogService->log(
            action: AuditAction::UPDATED,
            auditable: $process,
            beforeValues: ['telegram_bot_token' => 'bot123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11', 'cookie' => 'session=secret123'],
            afterValues: ['telegram_bot_token' => 'bot999999:XYZ-DEF1234ghIkl-zyx57W2v1u123ew99', 'session_token' => 'tok999'],
            metadata: ['auth_key' => 'super_secret', 'safe_info' => 'all clear']
        );

        $this->assertArrayNotHasKey('telegram_bot_token', $log->before_values);
        $this->assertArrayNotHasKey('cookie', $log->before_values);
        $this->assertArrayNotHasKey('telegram_bot_token', $log->after_values);
        $this->assertArrayNotHasKey('session_token', $log->after_values);
        $this->assertArrayNotHasKey('auth_key', $log->metadata);
        $this->assertEquals('all clear', $log->metadata['safe_info']);
    }

    // =========================================================================
    // 8. SCHEDULER & COMMAND IDEMPOTENCY VERIFICATION
    // =========================================================================

    public function test_all_background_commands_are_strictly_idempotent(): void
    {
        $department = Department::create(['name' => 'Audit Dept', 'code' => 'AUD']);
        $process = $this->processService->createProcess([
            'department_id' => $department->id,
            'name' => 'Idempotency Validation Process',
            'responsible_user_id' => $this->employeeA->id,
            'frequency' => ProcessFrequency::DAILY->value,
            'reminder_time' => '09:00',
        ], [
            ['question' => 'System Check?', 'response_type' => ProcessResponseType::YES_NO->value, 'is_required' => true, 'sort_order' => 1],
        ], [
            ['level' => 1, 'delay_minutes' => 30, 'escalate_to_user_id' => $this->manager->id, 'is_active' => true],
        ]);

        // Run processes:process 5 consecutive times
        for ($i = 0; $i < 5; $i++) {
            $this->artisan('processes:process')->assertExitCode(0);
        }
        $this->assertEquals(1, ProcessExecution::where('process_id', $process->id)->count());

        // Run processes:status 5 consecutive times
        for ($i = 0; $i < 5; $i++) {
            $this->artisan('processes:status')->assertExitCode(0);
        }
        $this->assertEquals(1, ProcessExecution::where('process_id', $process->id)->count());

        // Advance time past escalation and run processes:escalate 5 times
        Carbon::setTestNow(Carbon::now()->addMinutes(45));
        for ($i = 0; $i < 5; $i++) {
            $this->artisan('processes:escalate')->assertExitCode(0);
        }
        $this->assertEquals(1, ProcessEscalationEvent::count());

        // Run reminders:process and recurring-tasks:process multiple times
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('reminders:process')->assertExitCode(0);
            $this->artisan('recurring-tasks:process')->assertExitCode(0);
        }
    }
}
