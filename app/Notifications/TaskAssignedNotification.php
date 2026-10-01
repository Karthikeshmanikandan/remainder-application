<?php

namespace App\Notifications;

use App\Models\Task;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Task $task,
        public readonly bool $isReassignment = false
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        // In-app notification preference check (default: true)
        $prefs = $notifiable->notificationPreference;
        if (! $prefs || $prefs->in_app_enabled) {
            $channels[] = 'database';
        }

        // TelegramChannel checks verification and telegram_enabled internally
        $channels[] = TelegramChannel::class;

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $project = $this->task->project;
        $assigner = $this->task->creator;

        return [
            'type' => 'task_assigned',
            'task_id' => $this->task->id,
            'task_code' => $this->task->code,
            'task_title' => $this->task->title,
            'task_url' => route('tasks.show', $this->task),
            'project_id' => $project?->id,
            'project_name' => $project?->name,
            'priority' => $this->task->priority->value,
            'due_date' => $this->task->due_date?->toIso8601String(),
            'assigned_by' => $assigner?->name ?? 'System',
            'message' => "New task assigned: {$this->task->code} - {$this->task->title}",
        ];
    }

    public function toTelegram(object $notifiable): array
    {
        $project = $this->task->project;
        $assigner = $this->task->creator;

        return [
            'header' => '📋 <b>New Task Assigned</b>',
            'intro' => 'You have been assigned a new task.',
            'task_code' => htmlspecialchars($this->task->code),
            'task_title' => htmlspecialchars($this->task->title),
            'project_name' => $project ? htmlspecialchars($project->name) : 'None',
            'priority' => strtoupper($this->task->priority->value),
            'due_date' => $this->task->due_date?->format('d M Y, H:i T') ?? 'No deadline',
            'assigned_by' => $assigner ? htmlspecialchars($assigner->name) : 'System',
            'task_url' => route('tasks.show', $this->task),
        ];
    }
}
