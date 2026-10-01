<?php

namespace App\Console\Commands;

use App\Services\RecurringTaskService;
use Illuminate\Console\Command;

class ProcessRecurringTasks extends Command
{
    protected $signature = 'recurring-tasks:process';

    protected $description = 'Process all active due recurring task definitions and generate occurrences.';

    public function handle(RecurringTaskService $service): int
    {
        $this->info('Processing recurring tasks...');

        $result = $service->processDueRecurringTasks();

        $this->line('');
        $this->line("  <fg=green>Generated:</>  {$result['generated']}");
        $this->line("  <fg=yellow>Skipped:</>    {$result['skipped']}");
        if ($result['duplicates_prevented'] > 0) {
            $this->line("  <fg=red>Duplicates prevented:</> {$result['duplicates_prevented']}");
        } else {
            $this->line('  <fg=gray>Duplicates prevented:</> 0');
        }
        $this->line('');

        if ($result['generated'] === 0) {
            $this->info('No recurring tasks were due at this time.');
        } else {
            $this->info("Generated {$result['generated']} task(s).");
        }

        return self::SUCCESS;
    }
}
