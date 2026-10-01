<?php

namespace App\Notifications;

use App\Models\Process;
use App\Models\ProcessEscalationEvent;
use App\Models\ProcessExecution;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProcessEscalationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Process $process,
        public readonly ProcessExecution $execution,
        public readonly ProcessEscalationEvent $event
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Add Telegram channel if the recipient has Telegram enabled and process allows telegram
        $prefs = $notifiable->notificationPreference;
        if ($this->process->telegram_enabled && (! $prefs || $prefs->telegram_enabled)) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $dept = $this->process->department;

        return [
            'type' => 'process_escalation',
            'process_id' => $this->process->id,
            'process_execution_id' => $this->execution->id,
            'escalation_event_id' => $this->event->id,
            'level' => $this->event->level,
            'process_name' => $this->process->name,
            'process_code' => $this->process->code,
            'department_name' => $dept?->name ?? 'General',
            'responsible_name' => $this->process->responsibleUser->name,
            'scheduled_for' => $this->execution->scheduled_for->toIso8601String(),
            'url' => route('process-executions.show', $this->execution),
            'message' => "🚨 Level {$this->event->level} Escalation: {$this->process->name} remains unconfirmed.",
        ];
    }

    public function toTelegram(object $notifiable): array
    {
        $dept = $this->process->department;

        return [
            'header' => '🚨 <b>Process Confirmation Escalation</b>',
            'intro' => 'Required human confirmation was not recorded within the configured escalation period.',
            'task_title' => htmlspecialchars($this->process->name),
            'project_name' => $dept ? htmlspecialchars($dept->name) : 'General',
            'priority' => "Level {$this->event->level} Escalation",
            'due_date' => $this->execution->scheduled_for->format('d M Y, H:i T'),
            'assigned_by' => htmlspecialchars($this->process->responsibleUser->name),
            'message' => '<b>Reason:</b> Required confirmation has not been recorded within the configured escalation period.',
            'task_url' => route('process-executions.show', $this->execution),
        ];
    }
}
