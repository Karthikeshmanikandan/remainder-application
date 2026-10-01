<?php

namespace Tests\Feature;

use App\Enums\ProcessEscalationStatus;
use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessFrequency;
use App\Enums\ProcessItemStatus;
use App\Enums\ProcessResponseType;
use App\Enums\ProcessStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Process;
use App\Models\ProcessEscalationEvent;
use App\Models\ProcessEscalationRule;
use App\Models\ProcessExecution;
use App\Models\ProcessExecutionItem;
use App\Models\User;
use App\Notifications\ProcessEscalationNotification;
use App\Services\ProcessEscalationService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcessEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager;

    protected User $manager2;

    protected User $employee;

    protected Department $department;

    protected Process $process;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $this->manager2 = User::factory()->create(['role' => UserRole::MANAGER]);
        $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        $this->department = Department::create([
            'name' => 'Operations & Compliance',
            'code' => 'OPS',
            'description' => 'Operations Department',
        ]);

        $this->process = Process::create([
            'code' => 'OPS-DLY-001',
            'name' => 'Daily Safety Checklist',
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'frequency' => ProcessFrequency::DAILY,
            'reminder_time' => '17:00:00',
            'status' => ProcessStatus::ACTIVE,
            'next_run_at' => now(),
        ]);

        $this->process->items()->create([
            'order' => 1,
            'sort_order' => 1,
            'question' => 'Were safety locks verified?',
            'response_type' => ProcessResponseType::YES_NO,
            'is_required' => true,
            'is_enabled' => true,
        ]);
    }

    private function createExecution(array $attributes = []): ProcessExecution
    {
        $scheduledFor = $attributes['scheduled_for'] ?? now();
        $uniqueKey = uniqid($this->process->id.'-');

        $data = array_merge([
            'process_id' => $this->process->id,
            'scheduled_for' => $scheduledFor,
            'scheduled_date' => Carbon::parse($scheduledFor)->toDateString(),
            'scheduled_time' => Carbon::parse($scheduledFor)->toTimeString(),
            'due_at' => Carbon::parse($scheduledFor)->addDay(),
            'status' => ProcessExecutionStatus::PENDING,
            'occurrence_key' => $uniqueKey,
        ], $attributes);

        $execution = ProcessExecution::create($data);

        foreach ($this->process->items as $item) {
            ProcessExecutionItem::create([
                'process_execution_id' => $execution->id,
                'process_item_id' => $item->id,
                'question_snapshot' => $item->question,
                'response_type' => $item->response_type,
                'is_required' => $item->is_required,
                'sort_order' => $item->sort_order ?? $item->order ?? 1,
                'status' => ProcessItemStatus::PENDING,
            ]);
        }

        return $execution;
    }

    public function test_process_can_have_up_to_5_escalation_rules(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $rule = ProcessEscalationRule::create([
                'process_id' => $this->process->id,
                'level' => $i,
                'delay_minutes' => $i * 30,
                'escalate_to_user_id' => $this->manager->id,
                'is_active' => true,
            ]);

            $this->assertDatabaseHas('process_escalation_rules', [
                'id' => $rule->id,
                'level' => $i,
            ]);
        }

        $this->assertCount(5, $this->process->fresh()->escalationRules);
    }

    public function test_cannot_create_escalation_rule_exceeding_level_5(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('processes.store'), [
            'name' => 'Process with Invalid Level',
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'frequency' => 'daily',
            'escalation_rules' => [
                [
                    'level' => 6,
                    'delay_minutes' => 60,
                    'escalate_to_user_id' => $this->manager->id,
                    'is_active' => '1',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('escalation_rules.0.level');
    }

    public function test_escalation_rule_requires_delay_minutes_greater_than_zero(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('processes.store'), [
            'name' => 'Process with Zero Delay',
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'frequency' => 'daily',
            'escalation_rules' => [
                [
                    'level' => 1,
                    'delay_minutes' => 0,
                    'escalate_to_user_id' => $this->manager->id,
                    'is_active' => '1',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('escalation_rules.0.delay_minutes');
    }

    public function test_escalation_rule_delay_minutes_must_be_positive_integer(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('processes.store'), [
            'name' => 'Process with Negative Delay',
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'frequency' => 'daily',
            'escalation_rules' => [
                [
                    'level' => 1,
                    'delay_minutes' => -15,
                    'escalate_to_user_id' => $this->manager->id,
                    'is_active' => '1',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('escalation_rules.0.delay_minutes');
    }

    public function test_escalation_rule_recipient_must_be_admin_or_manager(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('processes.store'), [
            'name' => 'Process Valid Recipient',
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'frequency' => 'daily',
            'escalation_rules' => [
                [
                    'level' => 1,
                    'delay_minutes' => 30,
                    'escalate_to_user_id' => $this->manager->id,
                    'is_active' => '1',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('process_escalation_rules', [
            'escalate_to_user_id' => $this->manager->id,
            'level' => 1,
        ]);
    }

    public function test_escalation_rule_recipient_cannot_be_employee(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('processes.store'), [
            'name' => 'Process with Employee Recipient',
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'frequency' => 'daily',
            'escalation_rules' => [
                [
                    'level' => 1,
                    'delay_minutes' => 30,
                    'escalate_to_user_id' => $this->employee->id,
                    'is_active' => '1',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('escalation_rules.0.escalate_to_user_id');
    }

    public function test_process_store_request_validates_escalation_rules(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('processes.store'), [
            'name' => 'Store Process Test',
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee->id,
            'frequency' => 'daily',
            'escalation_rules' => [
                [
                    'level' => 1,
                    'delay_minutes' => 45,
                    'escalate_to_user_id' => $this->admin->id,
                    'is_active' => '1',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('process_escalation_rules', [
            'level' => 1,
            'delay_minutes' => 45,
            'escalate_to_user_id' => $this->admin->id,
        ]);
    }

    public function test_process_update_request_syncs_escalation_rules(): void
    {
        $this->actingAs($this->admin);

        ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $response = $this->put(route('processes.update', $this->process), [
            'name' => $this->process->name,
            'code' => $this->process->code,
            'responsible_user_id' => $this->employee->id,
            'frequency' => 'daily',
            'escalation_rules' => [
                [
                    'level' => 1,
                    'delay_minutes' => 60,
                    'escalate_to_user_id' => $this->manager2->id,
                    'is_active' => '1',
                ],
                [
                    'level' => 2,
                    'delay_minutes' => 120,
                    'escalate_to_user_id' => $this->admin->id,
                    'is_active' => '1',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('process_escalation_rules', [
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 60,
            'escalate_to_user_id' => $this->manager2->id,
        ]);
        $this->assertDatabaseHas('process_escalation_rules', [
            'process_id' => $this->process->id,
            'level' => 2,
            'delay_minutes' => 120,
            'escalate_to_user_id' => $this->admin->id,
        ]);
    }

    public function test_disabling_escalation_rule_deactivates_it(): void
    {
        $this->actingAs($this->admin);

        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $response = $this->put(route('processes.update', $this->process), [
            'name' => $this->process->name,
            'code' => $this->process->code,
            'responsible_user_id' => $this->employee->id,
            'frequency' => 'daily',
            'escalation_rules' => [
                [
                    'level' => 1,
                    'delay_minutes' => 30,
                    'escalate_to_user_id' => $this->manager->id,
                    'is_active' => '0',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('process_escalation_rules', [
            'id' => $rule->id,
            'is_active' => false,
        ]);
    }

    public function test_process_has_escalation_enabled_helper_returns_correct_boolean(): void
    {
        $this->assertFalse($this->process->hasEscalationEnabled());

        ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $this->assertTrue($this->process->fresh()->hasEscalationEnabled());
    }

    public function test_process_escalation_command_runs_successfully(): void
    {
        $this->artisan('processes:escalate')
            ->expectsOutputToContain('Escalation processing complete.')
            ->assertSuccessful();
    }

    public function test_process_escalation_command_ignores_processes_without_escalation_rules(): void
    {
        $this->createExecution([
            'scheduled_for' => now()->subHours(2),
        ]);

        $this->artisan('processes:escalate')->assertSuccessful();

        $this->assertDatabaseCount('process_escalation_events', 0);
    }

    public function test_process_escalation_command_ignores_paused_and_cancelled_processes(): void
    {
        ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $this->process->update(['status' => ProcessStatus::PAUSED]);

        $this->createExecution([
            'scheduled_for' => now()->subHours(2),
        ]);

        $this->artisan('processes:escalate')->assertSuccessful();

        $this->assertDatabaseCount('process_escalation_events', 0);
    }

    public function test_process_escalation_command_ignores_completed_executions(): void
    {
        ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $this->createExecution([
            'scheduled_for' => now()->subHours(2),
            'status' => ProcessExecutionStatus::COMPLETED,
            'completed_at' => now()->subHour(),
            'completed_by' => $this->employee->id,
        ]);

        $this->artisan('processes:escalate')->assertSuccessful();

        $this->assertDatabaseCount('process_escalation_events', 0);
    }

    public function test_process_escalation_command_ignores_executions_not_yet_exceeding_delay(): void
    {
        ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 60,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        // Scheduled 10 minutes ago, delay is 60 minutes
        $this->createExecution([
            'scheduled_for' => now()->subMinutes(10),
        ]);

        $this->artisan('processes:escalate')->assertSuccessful();

        $this->assertDatabaseCount('process_escalation_events', 0);
    }

    public function test_process_escalation_command_triggers_level_1_when_delay_exceeded(): void
    {
        Notification::fake();

        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(45),
        ]);

        $this->artisan('processes:escalate')->assertSuccessful();

        $this->assertDatabaseHas('process_escalation_events', [
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'status' => ProcessEscalationStatus::TRIGGERED->value,
        ]);

        Notification::assertSentTo($this->manager, ProcessEscalationNotification::class);
    }

    public function test_process_escalation_command_is_idempotent_and_does_not_duplicate_events(): void
    {
        Notification::fake();

        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(45),
        ]);

        $this->artisan('processes:escalate')->assertSuccessful();
        $this->assertDatabaseCount('process_escalation_events', 1);

        // Run again immediately
        $this->artisan('processes:escalate')->assertSuccessful();
        $this->assertDatabaseCount('process_escalation_events', 1);

        Notification::assertSentTimes(ProcessEscalationNotification::class, 1);
    }

    public function test_process_escalation_command_triggers_level_2_after_level_2_delay_exceeded(): void
    {
        Notification::fake();

        $rule1 = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $rule2 = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 2,
            'delay_minutes' => 60,
            'escalate_to_user_id' => $this->admin->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(75),
        ]);

        $this->artisan('processes:escalate')->assertSuccessful();

        $this->assertDatabaseCount('process_escalation_events', 2);
        $this->assertDatabaseHas('process_escalation_events', [
            'process_execution_id' => $execution->id,
            'level' => 1,
        ]);
        $this->assertDatabaseHas('process_escalation_events', [
            'process_execution_id' => $execution->id,
            'level' => 2,
        ]);

        Notification::assertSentTo($this->manager, ProcessEscalationNotification::class);
        Notification::assertSentTo($this->admin, ProcessEscalationNotification::class);
    }

    public function test_process_escalation_command_triggers_multiple_levels_in_proper_order(): void
    {
        $rule1 = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $rule2 = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 2,
            'delay_minutes' => 60,
            'escalate_to_user_id' => $this->admin->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(40),
        ]);

        // At 40 mins past due: Level 1 triggers, Level 2 does not yet
        $this->artisan('processes:escalate')->assertSuccessful();
        $this->assertDatabaseCount('process_escalation_events', 1);
        $this->assertDatabaseHas('process_escalation_events', ['level' => 1]);

        // Update scheduled time to 70 mins past due
        $execution->update(['scheduled_for' => now()->subMinutes(70)]);
        $this->artisan('processes:escalate')->assertSuccessful();
        $this->assertDatabaseCount('process_escalation_events', 2);
        $this->assertDatabaseHas('process_escalation_events', ['level' => 2]);
    }

    public function test_escalation_event_records_correct_level_and_triggered_timestamp(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(20),
        ]);

        $this->artisan('processes:escalate')->assertSuccessful();

        $event = ProcessEscalationEvent::where('process_execution_id', $execution->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals(1, $event->level);
        $this->assertNotNull($event->triggered_at);
        $this->assertEquals(ProcessEscalationStatus::TRIGGERED, $event->status);
    }

    public function test_escalation_event_status_is_initially_triggered(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $this->createExecution([
            'scheduled_for' => now()->subMinutes(20),
        ]);

        $this->artisan('processes:escalate')->assertSuccessful();

        $event = ProcessEscalationEvent::first();
        $this->assertTrue($event->isTriggered());
        $this->assertFalse($event->isAcknowledged());
        $this->assertFalse($event->isResolved());
    }

    public function test_escalation_dispatches_in_app_notification_to_recipient(): void
    {
        Notification::fake();

        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(20),
        ]);

        $this->artisan('processes:escalate')->assertSuccessful();

        Notification::assertSentTo($this->manager, ProcessEscalationNotification::class, function ($notification) use ($execution) {
            $data = $notification->toArray($this->manager);

            return $data['process_execution_id'] === $execution->id
                && $data['process_id'] === $this->process->id
                && $data['level'] === 1;
        });
    }

    public function test_escalation_notification_contains_factual_neutral_language(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(20),
        ]);

        $event = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $notification = new ProcessEscalationNotification($this->process, $execution, $event);
        $telegramData = $notification->toTelegram($this->manager);
        $arrayData = $notification->toArray($this->manager);

        $this->assertStringContainsString('Required human confirmation was not recorded', $telegramData['intro']);
        $this->assertStringNotContainsString('failed to complete', $arrayData['message']);
        $this->assertStringNotContainsString('employee failed', $arrayData['message']);
    }

    public function test_escalation_notification_contains_correct_process_and_execution_links(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(20),
        ]);

        $event = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $notification = new ProcessEscalationNotification($this->process, $execution, $event);
        $data = $notification->toArray($this->manager);

        $this->assertEquals(route('process-executions.show', $execution), $data['url']);
    }

    public function test_escalation_handles_gracefully_when_recipient_has_no_telegram_linked(): void
    {
        $this->assertNull($this->manager->telegram_chat_id);

        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(20),
        ]);

        // Should complete smoothly without throwing Telegram delivery errors
        $this->artisan('processes:escalate')->assertSuccessful();

        $this->assertDatabaseHas('process_escalation_events', [
            'process_execution_id' => $execution->id,
            'status' => ProcessEscalationStatus::TRIGGERED->value,
        ]);
    }

    public function test_recipient_manager_can_acknowledge_escalation_event(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(20),
        ]);

        $event = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->manager);

        $response = $this->post(route('accountability.escalations.acknowledge', $event));
        $response->assertRedirect();

        $event->refresh();
        $this->assertTrue($event->isAcknowledged());
        $this->assertEquals($this->manager->id, $event->acknowledged_by);
        $this->assertNotNull($event->acknowledged_at);
    }

    public function test_admin_can_acknowledge_any_escalation_event(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(20),
        ]);

        $event = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->admin);

        $response = $this->post(route('accountability.escalations.acknowledge', $event));
        $response->assertRedirect();

        $event->refresh();
        $this->assertTrue($event->isAcknowledged());
        $this->assertEquals($this->admin->id, $event->acknowledged_by);
    }

    public function test_employee_cannot_acknowledge_escalation_event(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(20),
        ]);

        $event = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->employee);

        $response = $this->post(route('accountability.escalations.acknowledge', $event));
        $response->assertForbidden();

        $event->refresh();
        $this->assertTrue($event->isTriggered());
    }

    public function test_acknowledging_already_acknowledged_event_is_safe(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(20),
        ]);

        $event = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'acknowledged_by' => $this->manager->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->post(route('accountability.escalations.acknowledge', $event));
        $response->assertRedirect();
    }

    public function test_completing_process_execution_resolves_all_active_escalation_events(): void
    {
        $rule1 = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $rule2 = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 2,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->admin->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(45),
        ]);

        $execItem = $execution->items->first();

        $event1 = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule1->id,
            'level' => 1,
            'triggered_at' => now()->subMinutes(30),
            'status' => ProcessEscalationStatus::ACKNOWLEDGED,
            'acknowledged_at' => now()->subMinutes(15),
            'acknowledged_by' => $this->manager->id,
        ]);

        $event2 = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule2->id,
            'level' => 2,
            'triggered_at' => now()->subMinutes(15),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->employee);

        // Employee completes the checklist
        $response = $this->post(route('process-executions.confirm', $execution), [
            'answers' => [
                $execItem->id => [
                    'response' => 'YES',
                    'notes' => 'Confirmed on site',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertTrue($execution->fresh()->isCompleted());

        $this->assertEquals(ProcessEscalationStatus::RESOLVED, $event1->fresh()->status);
        $this->assertEquals(ProcessEscalationStatus::RESOLVED, $event2->fresh()->status);
    }

    public function test_completing_unacknowledged_escalation_transitions_directly_to_resolved(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(45),
        ]);

        $execItem = $execution->items->first();

        $event = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now()->subMinutes(30),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->employee);

        $this->post(route('process-executions.confirm', $execution), [
            'answers' => [
                $execItem->id => [
                    'response' => 'YES',
                ],
            ],
        ]);

        $event->refresh();
        $this->assertTrue($event->isResolved());
    }

    public function test_resolved_escalation_events_are_not_retriggered_by_command(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(45),
            'status' => ProcessExecutionStatus::COMPLETED,
            'completed_at' => now(),
            'completed_by' => $this->employee->id,
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now()->subMinutes(30),
            'status' => ProcessEscalationStatus::RESOLVED,
        ]);

        $this->artisan('processes:escalate')->assertSuccessful();
        $this->assertDatabaseCount('process_escalation_events', 1);
    }

    public function test_manager_can_access_escalation_monitoring_index(): void
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('accountability.escalations.index'));
        $response->assertOk();
        $response->assertViewIs('accountability.escalations.index');
    }

    public function test_admin_can_access_escalation_monitoring_index(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('accountability.escalations.index'));
        $response->assertOk();
        $response->assertViewIs('accountability.escalations.index');
    }

    public function test_employee_cannot_access_escalation_monitoring_index(): void
    {
        $this->actingAs($this->employee);

        $response = $this->get(route('accountability.escalations.index'));
        $response->assertForbidden();
    }

    public function test_escalation_monitoring_can_filter_by_status(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 15,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(45),
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->manager);

        $response = $this->get(route('accountability.escalations.index', ['status' => 'triggered']));
        $response->assertOk();
        $response->assertSee('Triggered');

        $response2 = $this->get(route('accountability.escalations.index', ['status' => 'resolved']));
        $response2->assertOk();
        $response2->assertSee('No Escalation Events Found');
    }

    public function test_escalation_monitoring_can_filter_by_level(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 2,
            'delay_minutes' => 60,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(90),
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 2,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->manager);

        $response = $this->get(route('accountability.escalations.index', ['level' => 2]));
        $response->assertOk();
        $response->assertSee('Level 2');

        $response2 = $this->get(route('accountability.escalations.index', ['level' => 1]));
        $response2->assertOk();
        $response2->assertSee('No Escalation Events Found');
    }

    public function test_escalation_monitoring_can_filter_by_department(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(45),
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $otherDept = Department::create(['name' => 'HR & Admin', 'code' => 'HR']);

        $this->actingAs($this->manager);

        $response = $this->get(route('accountability.escalations.index', ['department_id' => $this->department->id]));
        $response->assertOk();
        $response->assertSee($this->process->name);

        $response2 = $this->get(route('accountability.escalations.index', ['department_id' => $otherDept->id]));
        $response2->assertOk();
        $response2->assertSee('No Escalation Events Found');
    }

    public function test_escalation_monitoring_can_filter_by_responsible_user(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(45),
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $otherEmployee = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        $this->actingAs($this->manager);

        $response = $this->get(route('accountability.escalations.index', ['responsible_user_id' => $this->employee->id]));
        $response->assertOk();
        $response->assertSee($this->employee->name);

        $response2 = $this->get(route('accountability.escalations.index', ['responsible_user_id' => $otherEmployee->id]));
        $response2->assertOk();
        $response2->assertSee('No Escalation Events Found');
    }

    public function test_escalation_monitoring_can_filter_by_recipient(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(45),
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->manager);

        $response = $this->get(route('accountability.escalations.index', ['recipient_id' => $this->manager->id]));
        $response->assertOk();
        $response->assertSee($this->manager->name);

        $response2 = $this->get(route('accountability.escalations.index', ['recipient_id' => $this->manager2->id]));
        $response2->assertOk();
        $response2->assertSee('No Escalation Events Found');
    }

    public function test_escalation_monitoring_can_filter_by_date_range(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subDays(5),
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now()->subDays(5),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->manager);

        $response = $this->get(route('accountability.escalations.index', ['date_range' => 'today']));
        $response->assertOk();
        $response->assertSee('No Escalation Events Found');

        $response2 = $this->get(route('accountability.escalations.index', ['date_range' => 'last_7_days']));
        $response2->assertOk();
        $response2->assertSee($this->process->name);
    }

    public function test_process_show_displays_escalation_rules_and_events(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 45,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(60),
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->admin);

        $response = $this->get(route('processes.show', $this->process));
        $response->assertOk();
        $response->assertSee('Configured Escalation Rules');
        $response->assertSee('45 minutes');
        $response->assertSee($this->manager->name);
    }

    public function test_process_execution_show_displays_escalation_history_and_acknowledge_button(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(45),
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->manager);

        $response = $this->get(route('process-executions.show', $execution));
        $response->assertOk();
        $response->assertSee('Escalation History');
        $response->assertSee('Required human confirmation was not recorded');
        $response->assertSee('Acknowledge');
    }

    public function test_dashboard_displays_escalation_summary_metrics_for_management(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution([
            'scheduled_for' => now()->subMinutes(45),
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->actingAs($this->manager);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Active Escalations');
        $response->assertSee('Unacknowledged');
    }

    public function test_escalation_rule_is_active_scope(): void
    {
        ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 2,
            'delay_minutes' => 60,
            'escalate_to_user_id' => $this->admin->id,
            'is_active' => false,
        ]);

        $this->assertCount(1, $this->process->activeEscalationRules);
        $this->assertEquals(1, $this->process->activeEscalationRules->first()->level);
    }

    public function test_escalation_event_status_scopes_and_helpers(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution(['scheduled_for' => now()->subMinutes(45)]);

        $event = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->assertTrue($event->isTriggered());
        $this->assertFalse($event->isAcknowledged());
        $this->assertFalse($event->isResolved());
        $this->assertFalse($event->isCancelled());

        $event->update([
            'status' => ProcessEscalationStatus::ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'acknowledged_by' => $this->manager->id,
        ]);

        $this->assertTrue($event->isAcknowledged());
        $this->assertFalse($event->isTriggered());

        $event->update(['status' => ProcessEscalationStatus::RESOLVED]);
        $this->assertTrue($event->isResolved());
    }

    public function test_escalation_event_unique_constraint_blocks_duplicates_at_db_level(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution(['scheduled_for' => now()->subMinutes(45)]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $this->expectException(QueryException::class);

        ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_escalation_monitoring(): void
    {
        $response = $this->get(route('accountability.escalations.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_acknowledge_escalation(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution(['scheduled_for' => now()->subMinutes(45)]);

        $event = ProcessEscalationEvent::create([
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $response = $this->post(route('accountability.escalations.acknowledge', $event));
        $response->assertRedirect(route('login'));
    }

    public function test_in_app_notification_stores_in_database_and_is_readable(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution(['scheduled_for' => now()->subMinutes(45)]);

        $this->artisan('processes:escalate')->assertSuccessful();

        $this->assertCount(1, $this->manager->notifications);
        $notification = $this->manager->notifications->first();
        $this->assertEquals('process_escalation', $notification->data['type']);
        $this->assertEquals(1, $notification->data['level']);
        $this->assertEquals($execution->id, $notification->data['process_execution_id']);
    }

    public function test_level_3_escalation_triggers_after_level_3_delay(): void
    {
        Notification::fake();

        $rule3 = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 3,
            'delay_minutes' => 180,
            'escalate_to_user_id' => $this->admin->id,
            'is_active' => true,
        ]);

        $execution = $this->createExecution(['scheduled_for' => now()->subMinutes(200)]);

        $this->artisan('processes:escalate')->assertSuccessful();

        $this->assertDatabaseHas('process_escalation_events', [
            'process_execution_id' => $execution->id,
            'escalation_rule_id' => $rule3->id,
            'level' => 3,
            'status' => ProcessEscalationStatus::TRIGGERED->value,
        ]);

        Notification::assertSentTo($this->admin, ProcessEscalationNotification::class);
    }

    public function test_escalation_command_outputs_summary_counts(): void
    {
        $rule = ProcessEscalationRule::create([
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $this->createExecution(['scheduled_for' => now()->subMinutes(45)]);

        $this->artisan('processes:escalate')
            ->expectsOutputToContain('Processed Executions')
            ->expectsOutputToContain('Escalations Triggered')
            ->assertSuccessful();
    }

    public function test_escalation_service_syncs_rules_cleanly(): void
    {
        $service = app(ProcessEscalationService::class);

        $rulesData = [
            [
                'level' => 1,
                'delay_minutes' => 45,
                'escalate_to_user_id' => $this->manager->id,
                'is_active' => '1',
            ],
            [
                'level' => 2,
                'delay_minutes' => 90,
                'escalate_to_user_id' => $this->admin->id,
                'is_active' => '1',
            ],
        ];

        $service->syncRulesForProcess($this->process, $rulesData);

        $this->assertCount(2, $this->process->fresh()->escalationRules);
        $this->assertDatabaseHas('process_escalation_rules', [
            'process_id' => $this->process->id,
            'level' => 1,
            'delay_minutes' => 45,
        ]);
        $this->assertDatabaseHas('process_escalation_rules', [
            'process_id' => $this->process->id,
            'level' => 2,
            'delay_minutes' => 90,
        ]);
    }

    public function test_sidebar_contains_accountability_escalations_link_for_managers_and_admins(): void
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee(route('accountability.escalations.index'));
    }
}
