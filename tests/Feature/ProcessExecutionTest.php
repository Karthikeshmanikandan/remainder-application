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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $employee1;

    protected User $employee2;

    protected Department $department;

    protected Process $process;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->employee1 = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $this->employee2 = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        $this->department = Department::create([
            'name' => 'Human Resources',
            'code' => 'HR',
        ]);

        $this->process = Process::create([
            'code' => 'HR-DLY-0001',
            'name' => 'Daily Attendance Check',
            'department_id' => $this->department->id,
            'responsible_user_id' => $this->employee1->id,
            'frequency' => ProcessFrequency::DAILY,
            'preferred_time' => '09:00:00',
            'status' => ProcessStatus::ACTIVE,
            'next_run_at' => now(),
        ]);

        $this->process->items()->createMany([
            [
                'order' => 1,
                'question' => 'Were biometric logs downloaded?',
                'response_type' => ProcessResponseType::YES_NO,
                'is_required' => true,
            ],
            [
                'order' => 2,
                'question' => 'Any employees missing swipe records?',
                'response_type' => ProcessResponseType::YES_NO_NA,
                'is_required' => false,
            ],
            [
                'order' => 3,
                'question' => 'Total staff count present today',
                'response_type' => ProcessResponseType::NUMBER,
                'is_required' => true,
            ],
        ]);
    }

    private function createSampleExecution(): ProcessExecution
    {
        $execution = ProcessExecution::create([
            'process_id' => $this->process->id,
            'scheduled_for' => now(),
            'due_at' => now()->addDay(),
            'status' => ProcessExecutionStatus::PENDING,
            'occurrence_key' => $this->process->id.'-'.now()->format('Y-m-d\TH:i'),
        ]);

        foreach ($this->process->items as $item) {
            ProcessExecutionItem::create([
                'process_execution_id' => $execution->id,
                'process_item_id' => $item->id,
                'question_snapshot' => $item->question,
                'response_type' => $item->response_type,
                'is_required' => $item->is_required,
                'status' => ProcessItemStatus::PENDING,
            ]);
        }

        return $execution;
    }

    public function test_employee_can_view_own_assigned_execution_checklist(): void
    {
        $execution = $this->createSampleExecution();

        $response = $this->actingAs($this->employee1)->get(route('process-executions.show', $execution));

        $response->assertOk();
        $response->assertSee('Daily Attendance Check');
        $response->assertSee('Were biometric logs downloaded?');
    }

    public function test_employee_cannot_view_or_answer_another_employees_execution(): void
    {
        $execution = $this->createSampleExecution();

        $response = $this->actingAs($this->employee2)->get(route('process-executions.show', $execution));
        $response->assertForbidden();

        $saveResponse = $this->actingAs($this->employee2)->post(route('process-executions.save-progress', $execution), [
            'answers' => [
                $execution->items->first()->id => ['response' => 'YES'],
            ],
        ]);
        $saveResponse->assertForbidden();

        $confirmResponse = $this->actingAs($this->employee2)->post(route('process-executions.confirm', $execution), [
            'answers' => [
                $execution->items->first()->id => ['response' => 'YES'],
            ],
        ]);
        $confirmResponse->assertForbidden();
    }

    public function test_admin_can_view_any_execution(): void
    {
        $execution = $this->createSampleExecution();

        $response = $this->actingAs($this->admin)->get(route('process-executions.show', $execution));

        $response->assertOk();
    }

    public function test_saving_progress_updates_status_to_in_progress_and_saves_responses(): void
    {
        $execution = $this->createSampleExecution();
        $firstItem = $execution->items->first();

        $response = $this->actingAs($this->employee1)->post(route('process-executions.save-progress', $execution), [
            'answers' => [
                $firstItem->id => [
                    'response' => 'YES',
                    'notes' => 'Downloaded successfully at 9:15 AM',
                ],
            ],
        ]);

        $response->assertRedirect(route('process-executions.show', $execution));
        $response->assertSessionHas('success');

        $execution->refresh();
        $firstItem->refresh();

        $this->assertEquals(ProcessExecutionStatus::IN_PROGRESS, $execution->status);
        $this->assertEquals('YES', $firstItem->response);
        $this->assertEquals('Downloaded successfully at 9:15 AM', $firstItem->notes);
        $this->assertEquals(ProcessItemStatus::ANSWERED, $firstItem->status);
        $this->assertNotNull($firstItem->answered_at);
    }

    public function test_confirming_without_answering_required_questions_fails_validation(): void
    {
        $execution = $this->createSampleExecution();
        $items = $execution->items;
        $requiredItem1 = $items[0]; // item 1 is required
        $requiredItem2 = $items[2]; // item 3 is required

        // Only answer one required item, leave item 3 empty
        $response = $this->actingAs($this->employee1)->post(route('process-executions.confirm', $execution), [
            'answers' => [
                $requiredItem1->id => [
                    'response' => 'YES',
                ],
                $requiredItem2->id => [
                    'response' => '',
                ],
            ],
        ]);

        $response->assertSessionHas('error');
        $execution->refresh();
        $this->assertNotEquals(ProcessExecutionStatus::COMPLETED, $execution->status);
    }

    public function test_confirming_all_required_questions_marks_execution_completed_and_records_audit(): void
    {
        $execution = $this->createSampleExecution();
        $items = $execution->items;

        $response = $this->actingAs($this->employee1)->post(route('process-executions.confirm', $execution), [
            'answers' => [
                $items[0]->id => ['response' => 'YES', 'notes' => 'All synced'],
                $items[1]->id => ['response' => 'NO'],
                $items[2]->id => ['response' => '42'],
            ],
            'confirmation_notes' => 'All attendance checks complete and verified.',
        ]);

        $response->assertRedirect(route('process-executions.show', $execution));
        $response->assertSessionHas('success');

        $execution->refresh();

        $this->assertEquals(ProcessExecutionStatus::COMPLETED, $execution->status);
        $this->assertEquals($this->employee1->id, $execution->completed_by);
        $this->assertNotNull($execution->completed_at);

        $execution->load('items');
        $this->assertEquals('All synced', $execution->items->first()->notes);

        foreach ($execution->items as $item) {
            $this->assertEquals(ProcessItemStatus::ANSWERED, $item->status);
            $this->assertNotNull($item->answered_at);
        }
    }

    public function test_completed_execution_cannot_be_re_confirmed_or_modified(): void
    {
        $execution = $this->createSampleExecution();
        $items = $execution->items;

        $this->actingAs($this->employee1)->post(route('process-executions.confirm', $execution), [
            'answers' => [
                $items[0]->id => ['response' => 'YES'],
                $items[1]->id => ['response' => 'NO'],
                $items[2]->id => ['response' => '50'],
            ],
        ]);

        $execution->refresh();
        $this->assertEquals(ProcessExecutionStatus::COMPLETED, $execution->status);

        // Attempting to modify completed execution
        $saveResponse = $this->actingAs($this->employee1)->post(route('process-executions.save-progress', $execution), [
            'answers' => [
                $items[0]->id => ['response' => 'NO'],
            ],
        ]);
        $saveResponse->assertForbidden();

        $confirmResponse = $this->actingAs($this->employee1)->post(route('process-executions.confirm', $execution), [
            'answers' => [
                $items[0]->id => ['response' => 'NO'],
            ],
        ]);
        $confirmResponse->assertForbidden();
    }

    public function test_updating_process_items_does_not_alter_past_execution_question_snapshots(): void
    {
        $execution = $this->createSampleExecution();
        $originalSnapshot = $execution->items->first()->question_snapshot;

        // Process item is modified later
        $processItem = $this->process->items->first();
        $processItem->update(['question' => 'NEW QUESTION: Were the biometrics synchronized with cloud?']);

        $execution->refresh();
        $executionFirstItem = $execution->items()->first();

        $this->assertEquals($originalSnapshot, $executionFirstItem->question_snapshot);
        $this->assertEquals('Were biometric logs downloaded?', $executionFirstItem->question_snapshot);
    }
}
