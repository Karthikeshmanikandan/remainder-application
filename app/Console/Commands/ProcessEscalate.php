<?php

namespace App\Console\Commands;

use App\Services\ProcessEscalationService;
use Illuminate\Console\Command;

class ProcessEscalate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'processes:escalate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate unconfirmed process executions and trigger configured escalation rules';

    /**
     * Execute the console command.
     */
    public function handle(ProcessEscalationService $escalationService): int
    {
        $this->info('Evaluating unconfirmed process executions for escalation...');

        $stats = $escalationService->processEscalations();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed Executions', $stats['processed_executions']],
                ['Rules Evaluated', $stats['rules_evaluated']],
                ['Escalations Triggered', $stats['escalations_triggered']],
                ['Already Triggered', $stats['already_triggered']],
                ['Skipped', $stats['skipped']],
                ['Notification Failures', $stats['notification_failures']],
            ]
        );

        $this->info("Escalation processing complete. {$stats['escalations_triggered']} escalations triggered.");

        return Command::SUCCESS;
    }
}
