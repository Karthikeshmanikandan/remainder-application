<?php

namespace Tests\Feature;

use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessFrequency;
use App\Enums\ProcessStatus;
use App\Models\Department;
use App\Models\NotificationPreference;
use App\Models\Process;
use App\Models\ProcessExecution;
use App\Models\ProcessItem;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Notifications\ProcessDueNotification;
use App\Services\ProcessScheduleService;
use App\Services\ProcessService;
use Carbon\Carbon;
use Database\Seeders\DepartmentAndProcessTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DepartmentAndProcessTemplateSeeder::class);
    }

    public function test_due_process_generates_execution_with_question_snapshots(): void
    {
        $employee = User::factory()->create();
        $department = Department::first();

        $process = Process::create([
            'department_id' => $department->id,
            'name' => 'Morning Verification',
            'code' => 'MORN-01',
            'responsible_user_id' => $employee->id,
            'frequency' => ProcessFrequency::DAILY,
            'interval' => 1,
            'reminder_time' => '09:00',
            'next_run_at' => now()->subMinutes(5),
            'status' => ProcessStatus::ACTIVE,
        ]);

        ProcessItem::create([
            'process_id' => $process->id,
            'question' => 'Check server health?',
            'response_type' => 'yes_no',
            'sort_order' => 1,
            'is_required' => true,
            'is_enabled' => true,
        ]);

        ProcessItem::create([
            'process_id' => $process->id,
            'question' => 'Disabled question?',
            'response_type' => 'yes_no',
            'sort_order' => 2,
            'is_required' => false,
            'is_enabled' => false, // Disabled question should not be in execution
        ]);

        $service = app(ProcessScheduleService::class);
        $result = $service->processDueProcesses();

        $this->assertEquals(1, $result['generated']);

        $execution = ProcessExecution::where('process_id', $process->id)->first();
        $this->assertNotNull($execution);
        $this->assertEquals(ProcessExecutionStatus::PENDING, $execution->status);

        // Only enabled item should be copied
        $this->assertEquals(1, $execution->items()->count());
        $this->assertEquals('Check server health?', $execution->items()->first()->question_snapshot);

        // Next run was advanced
        $process->refresh();
        $this->assertTrue($process->next_run_at->gt(now()));
    }

    public function test_process_scheduling_command_is_idempotent(): void
    {
        $employee = User::factory()->create();
        $department = Department::first();

        $process = Process::create([
            'department_id' => $department->id,
            'name' => 'Idempotent Check',
            'code' => 'IDEMP-01',
            'responsible_user_id' => $employee->id,
            'frequency' => ProcessFrequency::DAILY,
            'interval' => 1,
            'next_run_at' => now()->subMinute(),
            'status' => ProcessStatus::ACTIVE,
        ]);

        ProcessItem::create([
            'process_id' => $process->id,
            'question' => 'Task ready?',
            'response_type' => 'yes_no',
            'is_enabled' => true,
        ]);

        $this->artisan('processes:process')
            ->assertSuccessful()
            ->expectsOutputToContain('Generated: 1');

        $this->assertEquals(1, ProcessExecution::where('process_id', $process->id)->count());

        // Running again without changing next_run_at should find nothing due
        $this->artisan('processes:process')
            ->assertSuccessful()
            ->expectsOutputToContain('Generated: 0');

        $this->assertEquals(1, ProcessExecution::where('process_id', $process->id)->count());
    }

    public function test_due_process_sends_in_app_and_telegram_notifications(): void
    {
        config(['services.telegram.bot_token' => 'test-token']);

        Http::fake([
            'api.telegram.org/bot*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 8888],
            ], 200),
        ]);

        $employee = User::factory()->create();
        TelegramAccount::create([
            'user_id' => $employee->id,
            'chat_id' => '998877',
            'verified_at' => now(),
            'is_active' => true,
        ]);
        NotificationPreference::create([
            'user_id' => $employee->id,
            'in_app_enabled' => true,
            'telegram_enabled' => true,
        ]);

        $department = Department::first();
        $process = Process::create([
            'department_id' => $department->id,
            'name' => 'Finance Verification Due',
            'code' => 'FIN-DUE-01',
            'responsible_user_id' => $employee->id,
            'frequency' => ProcessFrequency::DAILY,
            'interval' => 1,
            'reminder_time' => '17:00',
            'next_run_at' => now()->subMinutes(10),
            'status' => ProcessStatus::ACTIVE,
            'in_app_enabled' => true,
            'telegram_enabled' => true,
            'reminder_enabled' => true,
        ]);

        ProcessItem::create([
            'process_id' => $process->id,
            'question' => 'Bank transactions updated?',
            'response_type' => 'yes_no',
            'is_enabled' => true,
        ]);

        $service = app(ProcessScheduleService::class);
        $service->processDueProcesses();

        // In-app notification received
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $employee->id,
            'type' => ProcessDueNotification::class,
        ]);

        // Telegram message delivered
        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $employee->id,
            'channel' => 'telegram',
            'status' => 'sent',
            'provider_message_id' => '8888',
        ]);
    }

    public function test_monthly_process_uses_month_end_clamping(): void
    {
        $processService = app(ProcessService::class);

        // From 31 Jan -> +1 Month -> 28/29 Feb (clamped, not overflowing to March)
        $jan31 = Carbon::create(2026, 1, 31, 17, 0, 0);
        $nextRun = $processService->calculateNextRun(ProcessFrequency::MONTHLY, $jan31, 1, '17:00');

        $this->assertEquals(2, $nextRun->month);
        $this->assertEquals(28, $nextRun->day);
        $this->assertEquals(17, $nextRun->hour);
    }
}
