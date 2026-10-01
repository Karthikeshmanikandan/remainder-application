<?php

namespace App\Console\Commands;

use App\Services\ProcessScheduleService;
use Illuminate\Console\Command;

class ProcessProcesses extends Command
{
    protected $signature = 'processes:process';

    protected $description = 'Process due process definitions and generate checklist executions.';

    public function handle(ProcessScheduleService $scheduleService): int
    {
        $this->info('Processing due processes...');

        $stats = $scheduleService->processDueProcesses();

        $this->newLine();
        $this->line(" <fg=green>Generated:</> {$stats['generated']}");
        $this->line(" <fg=yellow>Skipped:</>   {$stats['skipped']}");
        $this->line(" <fg=cyan>Duplicates prevented:</> {$stats['duplicates_prevented']}");

        if ($stats['generated'] === 0 && $stats['skipped'] === 0 && $stats['duplicates_prevented'] === 0) {
            $this->comment('No processes were due at this time.');
        }

        return self::SUCCESS;
    }
}
