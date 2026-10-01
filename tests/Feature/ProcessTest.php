<?php

namespace Tests\Feature;

use App\Enums\ProcessFrequency;
use App\Enums\ProcessStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Process;
use App\Models\ProcessTemplate;
use App\Models\User;
use Database\Seeders\DepartmentAndProcessTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DepartmentAndProcessTemplateSeeder::class);
    }

    public function test_admin_can_create_process_from_template(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $template = ProcessTemplate::where('code', 'FIN_DAILY')->first();

        $response = $this->actingAs($admin)->post(route('processes.store'), [
            'department_id' => $template->department_id,
            'process_template_id' => $template->id,
            'name' => 'Finance Daily Routine',
            'code' => 'FIN-ROUTINE-01',
            'responsible_user_id' => $employee->id,
            'frequency' => ProcessFrequency::DAILY->value,
            'interval' => 1,
            'reminder_time' => '17:00',
            'in_app_enabled' => 1,
            'telegram_enabled' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('processes', [
            'code' => 'FIN-ROUTINE-01',
            'name' => 'Finance Daily Routine',
            'responsible_user_id' => $employee->id,
            'status' => ProcessStatus::ACTIVE->value,
        ]);

        $process = Process::where('code', 'FIN-ROUTINE-01')->first();
        $this->assertEquals($template->items()->count(), $process->items()->count());
    }

    public function test_employee_cannot_create_process(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $template = ProcessTemplate::where('code', 'SALES_DAILY')->first();

        $response = $this->actingAs($employee)->post(route('processes.store'), [
            'department_id' => $template->department_id,
            'process_template_id' => $template->id,
            'name' => 'Unauthorized Process',
            'responsible_user_id' => $employee->id,
            'frequency' => ProcessFrequency::DAILY->value,
        ]);

        $response->assertForbidden();
    }

    public function test_customer_can_disable_specific_questions_without_altering_original_template(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $template = ProcessTemplate::where('code', 'FIN_DAILY')->first();

        $items = [];
        foreach ($template->items as $index => $tItem) {
            $items[] = [
                'template_item_id' => $tItem->id,
                'question' => $tItem->question,
                'response_type' => $tItem->response_type->value,
                'sort_order' => $index + 1,
                'is_required' => true,
                'is_enabled' => $index !== 0, // Disable first question
            ];
        }

        $this->actingAs($admin)->post(route('processes.store'), [
            'department_id' => $template->department_id,
            'process_template_id' => $template->id,
            'name' => 'Customized Finance Process',
            'code' => 'CUST-FIN-01',
            'responsible_user_id' => $employee->id,
            'frequency' => ProcessFrequency::DAILY->value,
            'items' => $items,
        ]);

        $process = Process::where('code', 'CUST-FIN-01')->first();
        $this->assertEquals(count($items), $process->items()->count());
        $this->assertEquals(count($items) - 1, $process->enabledItems()->count());

        // Ensure original template is completely unchanged
        $this->assertTrue($template->items()->first()->is_active);
    }

    public function test_employee_can_view_own_assigned_process_but_not_others(): void
    {
        $employeeA = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $employeeB = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $department = Department::first();

        $processA = Process::create([
            'department_id' => $department->id,
            'name' => "Employee A's Process",
            'code' => 'PROC-A-01',
            'responsible_user_id' => $employeeA->id,
            'frequency' => ProcessFrequency::DAILY,
            'status' => ProcessStatus::ACTIVE,
        ]);

        // Employee A can view
        $responseA = $this->actingAs($employeeA)->get(route('processes.show', $processA));
        $responseA->assertSuccessful();
        $responseA->assertSee("Employee A's Process");

        // Employee B cannot view (IDOR protection)
        $responseB = $this->actingAs($employeeB)->get(route('processes.show', $processA));
        $responseB->assertForbidden();
    }

    public function test_admin_can_pause_resume_and_cancel_process(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $department = Department::first();

        $process = Process::create([
            'department_id' => $department->id,
            'name' => 'Status Test Process',
            'code' => 'STAT-01',
            'responsible_user_id' => $admin->id,
            'frequency' => ProcessFrequency::DAILY,
            'status' => ProcessStatus::ACTIVE,
        ]);

        // Pause
        $this->actingAs($admin)->patch(route('processes.pause', $process));
        $process->refresh();
        $this->assertEquals(ProcessStatus::PAUSED, $process->status);

        // Resume
        $this->actingAs($admin)->patch(route('processes.resume', $process));
        $process->refresh();
        $this->assertEquals(ProcessStatus::ACTIVE, $process->status);

        // Cancel
        $this->actingAs($admin)->patch(route('processes.cancel', $process));
        $process->refresh();
        $this->assertEquals(ProcessStatus::CANCELLED, $process->status);
    }
}
