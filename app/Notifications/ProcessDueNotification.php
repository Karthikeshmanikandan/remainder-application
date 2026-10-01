<?php

namespace App\Notifications;

use App\Models\Process;
use App\Models\ProcessExecution;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProcessDueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Process $process,
        public readonly ProcessExecution $execution
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        // Check in-app preference & process setting
        $prefs = $notifiable->notificationPreference;
        if ($this->process->in_app_enabled && (! $prefs || $prefs->in_app_enabled)) {
            $channels[] = 'database';
        }

        // Add Telegram channel if process has telegram enabled
        if ($this->process->telegram_enabled) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $dept = $this->process->department;

        return [
            'type' => 'process_due',
            'process_id' => $this->process->id,
            'process_execution_id' => $this->execution->id,
            'process_name' => $this->process->name,
            'process_code' => $this->process->code,
            'department_name' => $dept?->name ?? 'General',
            'scheduled_for' => $this->execution->scheduled_for->toIso8601String(),
            'url' => route('process-executions.show', $this->execution),
            'message' => "{$this->process->name} is due.",
        ];
    }

    public function toTelegram(object $notifiable): array
    {
        $dept = $this->process->department;
        $itemCount = $this->execution->items()->count();

        return [
            'header' => '📋 <b>Process Checklist Due</b>',
            'intro' => 'You are responsible for this checklist.',
            'task_title' => htmlspecialchars($this->process->name),
            'project_name' => $dept ? htmlspecialchars($dept->name) : 'General',
            'due_date' => $this->execution->scheduled_for->format('d M Y, H:i T'),
            'items' => $itemCount,
            'message' => "Please complete your checklist ({$itemCount} questions).",
            'task_url' => route('process-executions.show', $this->execution),
        ];
    }
}
