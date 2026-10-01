<?php

namespace App\Notifications;

use App\Models\TaskReminder;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly TaskReminder $reminder) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        // Check in-app preference (default true)
        $prefs = $notifiable->notificationPreference;
        if (! $prefs || $prefs->in_app_enabled) {
            $channels[] = 'database';
        }

        // Always add TelegramChannel, the channel itself will decide if user verified and opted in
        $channels[] = TelegramChannel::class;

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $task = $this->reminder->task;
        $project = $task->project;

        return [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'task_code' => $task->code,
            'task_url' => route('tasks.show', $task),
            'project_id' => $project->id,
            'project_name' => $project->name,
            'project_code' => $project->code,
            'reminder_id' => $this->reminder->id,
            'remind_at' => $this->reminder->remind_at->toIso8601String(),
            'due_date' => $task->due_date?->toIso8601String(),
            'message' => "Reminder: Task \"{$task->title}\" is due"
                .($task->due_date ? ' on '.$task->due_date->format('Y-m-d') : '')
                .'.',
        ];
    }

    public function toTelegram(object $notifiable): array
    {
        $task = $this->reminder->task;

        return [
            'task_title' => htmlspecialchars($task->title),
            'project_name' => $task->project ? htmlspecialchars($task->project->name) : 'None',
            'priority' => strtoupper($task->priority->value),
            'due_date' => $task->due_date?->format('d M Y, H:i T') ?? 'None',
            'remind_at' => $this->reminder->remind_at->format('d M Y, H:i T'),
            'message' => 'This is a scheduled task reminder.',
            'task_url' => route('tasks.show', $task->id),
        ];
    }
}
