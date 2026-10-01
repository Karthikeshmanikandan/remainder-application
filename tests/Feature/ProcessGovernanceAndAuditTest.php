<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ProcessEscalationStatus;
use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessFrequency;
use App\Enums\ProcessItemStatus;
use App\Enums\ProcessResponseType;
use App\Enums\ProcessStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Process;
use App\Models\ProcessEscalationEvent;
use App\Models\ProcessEscalationRule;
use App\Models\ProcessExecution;
use App\Models\ProcessExecutionItem;
use App\Models\ProcessItem;
use App\Models\ProcessTemplate;
use App\Models\ProcessTemplateItem;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ProcessEscalationService;
use App\Services\ProcessExecutionService;
use App\Services\ProcessService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProcessGovernanceAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager;

    protected User $employee;

    protected Department $department;

    protected ProcessTemplate $template;

    protected ProcessService $processService;

    protected AuditLogService $auditLogService;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 1, 10, 0, 0));

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        $this->department = Department::create([
            'name' => 'Operations',
            'code' => 'OPS',
            'description' => 'Operations Department',
            'is_active' => true,
        ]);

        $this->template = ProcessTemplate::create([
            'department_id' => $this->department->id,
            'name' => 'Daily Branch Opening',
            'code' => 'OPS-DBO',
            'description' => 'Standard opening procedures',
            'frequency_default' => ProcessFrequency::DAILY,
            'is_active' => true,
        ]);

        ProcessTemplateItem::create([
            'process_template_id' => $this->template->id,
            'question' => 'Is the main server online?',
            'response_type' => ProcessResponseType::YES_NO,
            'is_required' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->processService = app(ProcessService::class);
        $this->auditLogService = app(AuditLogService::class);
    }

    // =========================================================================
    // 1. Audit Log Append-Only & Immutability Rules
    // =========================================================================

    public function test_audit_logs_cannot_be_updated_at_model_level(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Initial creation',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Audit logs are append-only and cannot be modified.');

        $log->update(['summary' => 'Tampered summary']);
    }

    public function test_audit_logs_cannot_be_deleted_at_model_level(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Initial creation',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Audit logs are append-only and cannot be deleted.');

        $log->delete();
    }

    public function test_sensitive_attributes_are_scrubbed_from_audit_logs(): void
    {
        $this->actingAs($this->admin);

        $log = $this->auditLogService->log(
            action: AuditAction::UPDATED,
            auditable: $this->department,
            beforeValues: [
                'name' => 'Old OPS',
                'password' => 'secret-12345',
                'telegram_bot_token' => 'bot-tok-12345',
            ],
            afterValues: [
                'name' => 'New OPS',
                'password' => 'new-secret-999',
                'telegram_bot_token' => 'new-bot-tok-999',
            ],
            summary: 'Updated sensitive credentials test',
            metadata: ['auth_token' => 'jwt.token.string', 'safe_note' => 'all clear']
        );

        // Sensitive keys omitted by recursive sanitizer
        $this->assertArrayNotHasKey('password', $log->before_values);
        $this->assertArrayNotHasKey('telegram_bot_token', $log->before_values);
        $this->assertEquals('Old OPS', $log->before_values['name']);

        $this->assertArrayNotHasKey('password', $log->after_values);
        $this->assertArrayNotHasKey('telegram_bot_token', $log->after_values);
        $this->assertEquals('New OPS', $log->after_values['name']);

        $this->assertArrayNotHasKey('auth_token', $log->metadata);
        $this->assertEquals('all clear', $log->metadata['safe_note']);
    }

    // =========================================================================
    // 2. Department Governance & Protection
    // =========================================================================

    public function test_admin_can_view_and_create_department(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.departments.store'), [
            'name' => 'Human Resources',
            'code' => 'HR',
            'description' => 'Handles personnel',
            'is_active' => '1',
        ]);

        $dept = Department::where('code', 'HR')->first();
        $response->assertRedirect(route('admin.departments.show', $dept));
        $this->assertDatabaseHas('departments', ['code' => 'HR', 'name' => 'Human Resources']);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Department::class,
            'auditable_id' => $dept->id,
            'action' => AuditAction::CREATED->value,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_update_department_and_it_is_audited(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.departments.update', $this->department), [
            'name' => 'Operations & Logistics',
            'code' => 'OPS-LOG',
            'description' => 'Updated desc',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.departments.show', $this->department));
        $this->department->refresh();
        $this->assertEquals('Operations & Logistics', $this->department->name);
        $this->assertEquals('OPS-LOG', $this->department->code);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'action' => AuditAction::UPDATED->value,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_toggle_department_active_status(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.departments.toggle-active', $this->department));
        $response->assertRedirect();
        $this->department->refresh();
        $this->assertFalse($this->department->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'action' => AuditAction::DEACTIVATED->value,
        ]);

        $this->actingAs($this->admin)->patch(route('admin.departments.toggle-active', $this->department));
        $this->department->refresh();
        $this->assertTrue($this->department->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'action' => AuditAction::ACTIVATED->value,
        ]);
    }

    public function test_department_cannot_be_deleted_if_processes_exist(): void
    {
        Process::create([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Critical OPS Process',
            'code' => 'OPS-CRIT',
            'frequency' => ProcessFrequency::DAILY,
            'interval' => 1,
            'status' => ProcessStatus::ACTIVE,
            'next_run_at' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.departments.destroy', $this->department));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('departments', ['id' => $this->department->id]);
    }

    public function test_department_can_be_deleted_if_no_processes_exist_and_is_audited(): void
    {
        $emptyDept = Department::create(['name' => 'Empty Dept', 'code' => 'EMP']);

        $response = $this->actingAs($this->admin)->delete(route('admin.departments.destroy', $emptyDept));
        $response->assertRedirect(route('admin.departments.index'));
        $this->assertDatabaseMissing('departments', ['id' => $emptyDept->id]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Department::class,
            'auditable_id' => $emptyDept->id,
            'action' => AuditAction::DELETED->value,
        ]);
    }

    public function test_non_admin_cannot_access_department_governance(): void
    {
        $this->actingAs($this->manager)->get(route('admin.departments.index'))->assertStatus(403);
        $this->actingAs($this->employee)->get(route('admin.departments.index'))->assertStatus(403);
        $this->actingAs($this->employee)->post(route('admin.departments.store'), [
            'name' => 'Rogue Dept',
            'code' => 'ROGUE',
        ])->assertStatus(403);
    }

    // =========================================================================
    // 3. Process Template Governance
    // =========================================================================

    public function test_admin_and_manager_can_manage_process_templates(): void
    {
        $response = $this->actingAs($this->manager)->post(route('admin.process-templates.store'), [
            'department_id' => $this->department->id,
            'name' => 'Nightly Vault Lockdown',
            'code' => 'OPS-NVL',
            'description' => 'Security check',
            'frequency_default' => ProcessFrequency::DAILY->value,
            'is_active' => '1',
            'questions' => [
                [
                    'question' => 'Are all vaults sealed?',
                    'response_type' => ProcessResponseType::YES_NO->value,
                    'is_required' => '1',
                    'sort_order' => '1',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('process_templates', ['name' => 'Nightly Vault Lockdown']);

        $newTpl = ProcessTemplate::where('name', 'Nightly Vault Lockdown')->first();
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ProcessTemplate::class,
            'auditable_id' => $newTpl->id,
            'action' => AuditAction::CREATED->value,
            'user_id' => $this->manager->id,
        ]);
    }

    public function test_updating_process_template_creates_audit_log_with_changes(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.process-templates.update', $this->template), [
            'department_id' => $this->department->id,
            'name' => 'Daily Branch Opening (Revised)',
            'code' => 'OPS-DBO-R',
            'description' => 'Updated instructions',
            'frequency_default' => ProcessFrequency::DAILY->value,
            'is_active' => '1',
            'questions' => [
                [
                    'question' => 'Is the power backup armed?',
                    'response_type' => ProcessResponseType::YES_NO->value,
                    'is_required' => '1',
                    'sort_order' => '1',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->template->refresh();
        $this->assertEquals('Daily Branch Opening (Revised)', $this->template->name);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ProcessTemplate::class,
            'auditable_id' => $this->template->id,
            'action' => AuditAction::UPDATED->value,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_template_toggle_active_records_audit_log(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.process-templates.toggle-active', $this->template));
        $response->assertRedirect();
        $this->template->refresh();
        $this->assertFalse($this->template->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ProcessTemplate::class,
            'auditable_id' => $this->template->id,
            'action' => AuditAction::DEACTIVATED->value,
        ]);
    }

    public function test_employee_cannot_manage_templates(): void
    {
        $this->actingAs($this->employee)->get(route('admin.process-templates.index'))->assertStatus(403);
        $this->actingAs($this->employee)->post(route('admin.process-templates.store'), [
            'name' => 'Illegal Template',
            'department_id' => $this->department->id,
        ])->assertStatus(403);
    }

    // =========================================================================
    // 4. Process Configuration, Lifecycle & Reassignment Audits
    // =========================================================================

    public function test_process_creation_generates_audit_log(): void
    {
        $this->actingAs($this->admin);

        $process = $this->processService->createProcess([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Server Maintenance Routine',
            'frequency' => ProcessFrequency::WEEKLY->value,
            'reminder_time' => '18:00',
            'status' => ProcessStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::CREATED->value,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_process_update_detects_and_logs_exact_field_changes(): void
    {
        $this->actingAs($this->admin);

        $process = $this->processService->createProcess([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Initial Title',
            'frequency' => ProcessFrequency::DAILY->value,
            'reminder_time' => '09:00',
            'status' => ProcessStatus::ACTIVE->value,
        ]);

        $this->processService->updateProcess($process, [
            'name' => 'Updated Title',
            'reminder_time' => '10:30',
        ]);

        $log = AuditLog::where('auditable_type', Process::class)
            ->where('auditable_id', $process->id)
            ->where('action', AuditAction::UPDATED)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Initial Title', $log->before_values['name']);
        $this->assertEquals('Updated Title', $log->after_values['name']);
        $this->assertStringContainsString('name', $log->summary);
        $this->assertStringContainsString('reminder_time', $log->summary);
    }

    public function test_process_reassignment_creates_dedicated_assigned_action_audit(): void
    {
        $this->actingAs($this->admin);

        $newEmployee = User::factory()->create(['name' => 'Jane Smith', 'role' => UserRole::EMPLOYEE]);

        $process = $this->processService->createProcess([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Branch Closing',
            'frequency' => ProcessFrequency::DAILY->value,
            'reminder_time' => '21:00',
            'status' => ProcessStatus::ACTIVE->value,
        ]);

        $this->processService->updateProcess($process, [
            'responsible_user_id' => $newEmployee->id,
        ]);

        $reassignLog = AuditLog::where('auditable_type', Process::class)
            ->where('auditable_id', $process->id)
            ->where('action', AuditAction::REASSIGNED)
            ->first();

        $this->assertNotNull($reassignLog);
        $this->assertEquals($this->employee->id, $reassignLog->before_values['responsible_user_id']);
        $this->assertEquals($newEmployee->id, $reassignLog->after_values['responsible_user_id']);
        $this->assertStringContainsString('Responsible user changed', $reassignLog->summary);
        $this->assertStringContainsString('Jane Smith', $reassignLog->summary);
    }

    public function test_process_lifecycle_pause_resume_cancel_audits(): void
    {
        $this->actingAs($this->admin);

        $process = $this->processService->createProcess([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Daily Reconciliation',
            'frequency' => ProcessFrequency::DAILY->value,
            'status' => ProcessStatus::ACTIVE->value,
        ]);

        // Pause
        $this->processService->pauseProcess($process, 'System migration in progress');
        $this->assertEquals(ProcessStatus::PAUSED, $process->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::PAUSED->value,
        ]);

        // Resume
        $this->processService->resumeProcess($process);
        $this->assertEquals(ProcessStatus::ACTIVE, $process->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::RESUMED->value,
        ]);

        // Cancel
        $this->processService->cancelProcess($process, 'Discontinued legacy procedure');
        $this->assertEquals(ProcessStatus::CANCELLED, $process->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::CANCELLED->value,
        ]);
    }

    public function test_process_update_with_items_creates_audit_log(): void
    {
        $this->actingAs($this->admin);

        $process = $this->processService->createProcess([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Checklist Process',
            'frequency' => ProcessFrequency::DAILY->value,
            'status' => ProcessStatus::ACTIVE->value,
        ], [
            [
                'question' => 'Initial Question',
                'response_type' => ProcessResponseType::YES_NO->value,
                'is_required' => true,
                'sort_order' => 1,
            ],
        ]);

        $this->assertEquals(1, $process->items()->count());
        $item = $process->items->first();

        // Update items via updateProcess
        $this->processService->updateProcess($process, [
            'name' => 'Checklist Process Modified',
        ], [
            $item->id => [
                'is_enabled' => false,
                'is_required' => true,
            ],
        ]);

        $this->assertFalse($item->fresh()->is_enabled);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::CONFIGURED->value,
        ]);
    }

    // =========================================================================
    // 5. Escalation Rules & Acknowledgement Audits
    // =========================================================================

    public function test_escalation_rule_sync_and_audits(): void
    {
        $this->actingAs($this->admin);

        $process = $this->processService->createProcess([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Escalation Audited Process',
            'frequency' => ProcessFrequency::DAILY->value,
            'status' => ProcessStatus::ACTIVE->value,
        ]);

        $escalationService = app(ProcessEscalationService::class);
        $escalationService->syncRulesForProcess($process, [
            [
                'level' => 1,
                'delay_minutes' => 60,
                'escalate_to_user_id' => $this->manager->id,
                'is_active' => true,
            ],
        ]);

        $this->assertDatabaseHas('process_escalation_rules', [
            'process_id' => $process->id,
            'level' => 1,
            'delay_minutes' => 60,
        ]);
    }

    public function test_escalation_event_acknowledgement_is_audited(): void
    {
        $process = Process::create([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Escalation Ack Process',
            'code' => 'OPS-EAP',
            'frequency' => ProcessFrequency::DAILY,
            'interval' => 1,
            'status' => ProcessStatus::ACTIVE,
            'next_run_at' => Carbon::today(),
        ]);

        $execution = ProcessExecution::create([
            'process_id' => $process->id,
            'occurrence_key' => '2026-10-01-OPS-EAP',
            'scheduled_for' => Carbon::today()->setHour(9),
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $rule = ProcessEscalationRule::create([
            'process_id' => $process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
        ]);

        $event = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'status' => ProcessEscalationStatus::TRIGGERED,
            'triggered_at' => Carbon::now()->subMinutes(10),
        ]);

        $this->actingAs($this->manager);
        $escalationService = app(ProcessEscalationService::class);
        $escalationService->acknowledgeEscalation($event, $this->manager);

        $this->assertEquals(ProcessEscalationStatus::ACKNOWLEDGED, $event->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ProcessEscalationEvent::class,
            'auditable_id' => $event->id,
            'action' => AuditAction::ACKNOWLEDGED->value,
            'user_id' => $this->manager->id,
        ]);
    }

    // =========================================================================
    // 6. Execution Confirmation Audits & Historical Immutability
    // =========================================================================

    public function test_execution_confirmation_is_audited_with_factual_actor_record(): void
    {
        $process = Process::create([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'End of Day Signoff',
            'code' => 'OPS-EOD',
            'frequency' => ProcessFrequency::DAILY,
            'interval' => 1,
            'status' => ProcessStatus::ACTIVE,
            'next_run_at' => Carbon::today(),
        ]);

        $item = ProcessItem::create([
            'process_id' => $process->id,
            'question' => 'Is the main breaker switched off?',
            'response_type' => ProcessResponseType::YES_NO,
            'is_required' => true,
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $execution = ProcessExecution::create([
            'process_id' => $process->id,
            'occurrence_key' => '2026-10-01-OPS-EOD',
            'scheduled_for' => Carbon::today()->setHour(17),
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $execItem = ProcessExecutionItem::create([
            'process_execution_id' => $execution->id,
            'process_item_id' => $item->id,
            'question_snapshot' => $item->question,
            'response_type' => $item->response_type,
            'status' => ProcessItemStatus::PENDING,
        ]);

        $this->actingAs($this->employee);
        $executionService = app(ProcessExecutionService::class);
        $result = $executionService->confirmExecution(
            $execution,
            [
                $execItem->id => [
                    'response' => 'yes',
                    'notes' => 'Verified switch in room 102',
                ],
            ],
            $this->employee
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(ProcessExecutionStatus::COMPLETED, $execution->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ProcessExecution::class,
            'auditable_id' => $execution->id,
            'action' => AuditAction::COMPLETED->value,
            'user_id' => $this->employee->id,
        ]);
    }

    public function test_modifying_process_or_template_does_not_mutate_past_executions(): void
    {
        $process = $this->processService->createProcess([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Immutable Past Check',
            'frequency' => ProcessFrequency::DAILY->value,
            'status' => ProcessStatus::ACTIVE->value,
        ], [
            [
                'question' => 'Original Historical Question',
                'response_type' => ProcessResponseType::YES_NO->value,
                'is_required' => true,
                'sort_order' => 1,
            ],
        ]);

        $item = $process->items->first();

        $execution = ProcessExecution::create([
            'process_id' => $process->id,
            'occurrence_key' => '2026-10-01-OPS-IPC',
            'scheduled_for' => Carbon::today()->setHour(17),
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $execItem = ProcessExecutionItem::create([
            'process_execution_id' => $execution->id,
            'process_item_id' => $item->id,
            'question_snapshot' => $item->question,
            'response_type' => $item->response_type,
            'status' => ProcessItemStatus::PENDING,
        ]);

        $this->actingAs($this->employee);
        $executionService = app(ProcessExecutionService::class);
        $executionService->confirmExecution(
            $execution,
            [
                $execItem->id => [
                    'response' => 'yes',
                    'notes' => 'Original human confirmation',
                ],
            ],
            $this->employee
        );

        // Later, Admin alters the process and item
        $this->actingAs($this->admin);
        $this->processService->updateProcess($process, ['name' => 'Altered Future Title']);
        $item->update(['question' => 'Altered Future Question Text']);

        // Verify past execution and snapshot questions remained untouched
        $execItem->refresh();
        $this->assertEquals('Original Historical Question', $execItem->question_snapshot);
        $this->assertEquals('yes', $execItem->response);
        $this->assertEquals('Original human confirmation', $execItem->notes);
    }

    // =========================================================================
    // 7. Audit Log Viewer UI & RBAC Authorization
    // =========================================================================

    public function test_admin_can_view_and_filter_audit_logs(): void
    {
        $log1 = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Department Created',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        AuditLog::create([
            'user_id' => $this->manager->id,
            'action' => AuditAction::PAUSED,
            'auditable_type' => Process::class,
            'auditable_id' => 999,
            'summary' => 'Process Paused',
            'created_at' => Carbon::now(),
        ]);

        // Admin index
        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.index'));
        $response->assertStatus(200);
        $response->assertSee('System Audit');
        $response->assertSee('Department Created');
        $response->assertSee('Process Paused');

        // Filter by Action
        $filterResponse = $this->actingAs($this->admin)->get(route('admin.audit-logs.index', ['action' => AuditAction::PAUSED->value]));
        $filterResponse->assertStatus(200);
        $filterResponse->assertSee('Process Paused');
        $filterResponse->assertDontSee('Department Created');

        // Show diff inspection modal/page
        $showResponse = $this->actingAs($this->admin)->get(route('admin.audit-logs.show', $log1));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Audit Log Inspection');
        $showResponse->assertSee('Department Created');
    }

    public function test_non_admin_cannot_access_audit_log_viewer(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Secret Log',
        ]);

        $this->actingAs($this->manager)->get(route('admin.audit-logs.index'))->assertStatus(403);
        $this->actingAs($this->manager)->get(route('admin.audit-logs.show', $log))->assertStatus(403);

        $this->actingAs($this->employee)->get(route('admin.audit-logs.index'))->assertStatus(403);
        $this->actingAs($this->employee)->get(route('admin.audit-logs.show', $log))->assertStatus(403);
    }

    public function test_audit_logs_have_no_edit_or_delete_routes(): void
    {
        $this->assertFalse(Route::has('admin.audit-logs.edit'));
        $this->assertFalse(Route::has('admin.audit-logs.update'));
        $this->assertFalse(Route::has('admin.audit-logs.destroy'));
    }

    public function test_process_show_page_renders_audit_history_section(): void
    {
        $this->actingAs($this->admin);

        $process = $this->processService->createProcess([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Audit Display Process',
            'frequency' => ProcessFrequency::DAILY->value,
            'status' => ProcessStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($this->admin)->get(route('processes.show', $process));
        $response->assertStatus(200);
        $response->assertSee('Configuration and Governance History');
        $response->assertSee('Audit Display Process');
    }

    // =========================================================================
    // 8. Advanced Filters, Search & Governance Edge Cases
    // =========================================================================

    public function test_audit_logs_can_be_filtered_by_target_entity(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Dept Filter Test',
        ]);

        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Process::class,
            'auditable_id' => 123,
            'summary' => 'Process Filter Test',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.index', ['auditable_type' => 'Department']));
        $response->assertStatus(200);
        $response->assertSee('Dept Filter Test');
        $response->assertDontSee('Process Filter Test');
    }

    public function test_audit_logs_can_be_filtered_by_user_actor(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Admin Performed Log',
        ]);

        AuditLog::create([
            'user_id' => $this->manager->id,
            'action' => AuditAction::UPDATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Manager Performed Log',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.index', ['user_id' => $this->manager->id]));
        $response->assertStatus(200);
        $response->assertSee('Manager Performed Log');
        $response->assertDontSee('Admin Performed Log');
    }

    public function test_audit_logs_can_be_filtered_by_date_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Old Date Log',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Recent Date Log',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.index', [
            'date_from' => '2026-09-25',
            'date_to' => '2026-10-05',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Recent Date Log');
        $response->assertDontSee('Old Date Log');
    }

    public function test_audit_logs_can_be_searched_by_keyword(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Alpha Bravo Charlie keyword',
        ]);

        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Delta Echo Foxtrot keyword',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.index', ['search' => 'Bravo']));
        $response->assertStatus(200);
        $response->assertSee('Alpha Bravo Charlie keyword');
        $response->assertDontSee('Delta Echo Foxtrot keyword');
    }

    public function test_department_show_page_displays_department_specific_audit_logs(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.departments.show', $this->department));
        $response->assertStatus(200);
        $response->assertSee('Department Governance Change History');
        $response->assertSee($this->department->name);
    }

    public function test_template_show_page_displays_template_specific_audit_logs(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.process-templates.show', $this->template));
        $response->assertStatus(200);
        $response->assertSee('Blueprint Governance Change History');
        $response->assertSee($this->template->name);
    }

    public function test_department_validation_fails_on_duplicate_code(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.departments.store'), [
            'name' => 'Duplicate Code Dept',
            'code' => 'OPS', // Already exists in setUp
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_template_validation_fails_on_duplicate_code(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.process-templates.store'), [
            'department_id' => $this->department->id,
            'name' => 'Duplicate Code Template',
            'code' => 'OPS-DBO', // Already exists in setUp
            'frequency_default' => ProcessFrequency::DAILY->value,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_template_cannot_be_deleted_if_processes_exist(): void
    {
        Process::create([
            'department_id' => $this->department->id,
            'process_template_id' => $this->template->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Tpl Bound Process',
            'code' => 'OPS-TBP',
            'frequency' => ProcessFrequency::DAILY,
            'interval' => 1,
            'status' => ProcessStatus::ACTIVE,
            'next_run_at' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.process-templates.destroy', $this->template));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('process_templates', ['id' => $this->template->id]);
    }

    public function test_template_can_be_deleted_if_no_processes_exist_and_is_audited(): void
    {
        $emptyTemplate = ProcessTemplate::create([
            'department_id' => $this->department->id,
            'name' => 'Empty Template',
            'code' => 'OPS-EMP',
            'frequency_default' => ProcessFrequency::DAILY,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.process-templates.destroy', $emptyTemplate));
        $response->assertRedirect(route('admin.process-templates.index'));
        $this->assertDatabaseMissing('process_templates', ['id' => $emptyTemplate->id]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ProcessTemplate::class,
            'auditable_id' => $emptyTemplate->id,
            'action' => AuditAction::DELETED->value,
        ]);
    }

    public function test_actor_ip_and_user_agent_are_captured_in_audit_logs(): void
    {
        $this->actingAs($this->admin);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.105',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 TestBrowser',
        ])->post(route('admin.departments.store'), [
            'name' => 'Captured IP Dept',
            'code' => 'CAP-IP',
            'is_active' => '1',
        ]);

        $dept = Department::where('code', 'CAP-IP')->first();
        $log = AuditLog::where('auditable_type', Department::class)
            ->where('auditable_id', $dept->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('192.168.1.105', $log->ip_address);
        $this->assertEquals('Mozilla/5.0 TestBrowser', $log->user_agent);
    }

    public function test_anonymous_action_logs_user_id_as_null(): void
    {
        $log = $this->auditLogService->log(
            action: AuditAction::CREATED,
            auditable: $this->department,
            summary: 'System daemon automated task',
            actor: null
        );

        $this->assertNull($log->user_id);
        $this->assertNull($log->user);
        $this->assertEquals('System daemon automated task', $log->summary);
    }

    public function test_action_enum_badge_classes_return_valid_tailwind_strings(): void
    {
        foreach (AuditAction::cases() as $action) {
            $badge = $action->badgeClass();
            $this->assertIsString($badge);
            $this->assertNotEmpty($badge);
            $this->assertStringContainsString('border', $badge);
        }
    }

    public function test_process_pause_and_resume_controller_actions_create_audit_logs(): void
    {
        $process = $this->processService->createProcess([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'Controller Action Process',
            'frequency' => ProcessFrequency::DAILY->value,
            'status' => ProcessStatus::ACTIVE->value,
        ]);

        // Pause via controller
        $this->actingAs($this->admin)->patch(route('processes.pause', $process));

        $this->assertEquals(ProcessStatus::PAUSED, $process->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::PAUSED->value,
        ]);

        // Resume via controller
        $this->actingAs($this->admin)->patch(route('processes.resume', $process));

        $this->assertEquals(ProcessStatus::ACTIVE, $process->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::RESUMED->value,
        ]);
    }

    public function test_process_cancel_controller_action_creates_audit_log(): void
    {
        $process = $this->processService->createProcess([
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'name' => 'To Be Cancelled Process',
            'frequency' => ProcessFrequency::DAILY->value,
            'status' => ProcessStatus::ACTIVE->value,
        ]);

        $this->actingAs($this->admin)->patch(route('processes.cancel', $process));

        $this->assertEquals(ProcessStatus::CANCELLED, $process->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Process::class,
            'auditable_id' => $process->id,
            'action' => AuditAction::CANCELLED->value,
        ]);
    }

    public function test_employee_cannot_pause_or_cancel_unassigned_process(): void
    {
        $otherEmployee = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        $process = $this->processService->createProcess([
            'department_id' => $this->department->id,
            'responsible_user_id' => $otherEmployee->id,
            'name' => 'Protected Process',
            'frequency' => ProcessFrequency::DAILY->value,
            'status' => ProcessStatus::ACTIVE->value,
        ]);

        $this->actingAs($this->employee)->patch(route('processes.pause', $process))->assertStatus(403);
        $this->actingAs($this->employee)->patch(route('processes.cancel', $process))->assertStatus(403);
    }

    public function test_sidebar_renders_governance_navigation_links_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Departments');
        $response->assertSee('Templates');
        $response->assertSee('Audit Trail');
    }

    public function test_sidebar_hides_audit_trail_for_employee(): void
    {
        $response = $this->actingAs($this->employee)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertDontSee('Audit Trail');
    }

    public function test_audit_log_helpers_and_scopes(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => Department::class,
            'auditable_id' => $this->department->id,
            'summary' => 'Department helper scope test',
        ]);

        $this->assertEquals('Department', $log->getEntityTypeName());
        $this->assertEquals('Operations', $log->getAuditableName());
        $this->assertEquals(1, AuditLog::forUser($this->admin->id)->count());
        $this->assertEquals(1, AuditLog::forAction(AuditAction::CREATED)->count());
        $this->assertEquals(1, AuditLog::forAuditable($this->department)->count());
    }

    public function test_audit_log_pagination_functions_correctly(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            AuditLog::create([
                'user_id' => $this->admin->id,
                'action' => AuditAction::CREATED,
                'auditable_type' => Department::class,
                'auditable_id' => $this->department->id,
                'summary' => "Paginated Log #{$i}",
            ]);
        }

        $logs = $this->auditLogService->getFilteredLogs([], 10);
        $this->assertEquals(25, $logs->total());
        $this->assertEquals(10, $logs->perPage());
        $this->assertEquals(3, $logs->lastPage());
    }
}
