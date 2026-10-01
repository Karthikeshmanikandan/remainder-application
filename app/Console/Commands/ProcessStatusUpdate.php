<?php

namespace App\Console\Commands;

use App\Services\ProcessExecutionStatusService;
use Illuminate\Console\Command;

class ProcessStatusUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'processes:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update status of past-due uncompleted process executions to MISSED';

    /**
     * Execute the console command.
     */
    public function handle(ProcessExecutionStatusService $statusService): int
    {
        $this->info('Scanning for past-due uncompleted process executions...');

        $missedCount = $statusService->markMissedExecutions();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Executions Marked as Missed', $missedCount],
            ]
        );

        $this->info("Process status update completed. {$missedCount} executions marked as missed.");

        return Command::SUCCESS;
    }
}
