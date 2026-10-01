<?php

namespace App\Console\Commands;

use App\Services\TaskReminderService;
use Illuminate\Console\Command;

class ProcessReminders extends Command
{
    protected $signature = 'reminders:process';

    protected $description = 'Process all pending due task reminders and dispatch in-app notifications.';

    public function handle(TaskReminderService $service): int
    {
        $this->info('Processing task reminders...');

        $result = $service->processDueReminders();

        $this->line('');
        $this->line("  <fg=green>Processed:</>  {$result['processed']}");
        $this->line("  <fg=yellow>Skipped:</>    {$result['skipped']}");
        $this->line('');

        if ($result['processed'] === 0) {
            $this->info('No reminders were due at this time.');
        } else {
            $this->info("Dispatched {$result['processed']} notification(s).");
        }

        return self::SUCCESS;
    }
}
