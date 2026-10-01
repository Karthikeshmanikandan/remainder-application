<?php

namespace Tests\Feature;

use App\Models\RecurringTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessRecurringTasksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_with_no_pending_recurrences(): void
    {
        $this->artisan('recurring-tasks:process')
            ->assertSuccessful()
            ->expectsOutput('Processing recurring tasks...')
            ->expectsOutputToContain('Generated:  0');
    }

    public function test_command_processes_due_recurrence(): void
    {
        RecurringTask::factory()->create(['next_run_at' => now()->subMinute()]);

        $this->artisan('recurring-tasks:process')
            ->assertSuccessful()
            ->expectsOutputToContain('Generated:  1');
    }
}
