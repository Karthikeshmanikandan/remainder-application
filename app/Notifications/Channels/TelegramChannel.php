<?php

namespace App\Notifications\Channels;

use App\Models\NotificationDelivery;
use App\Services\TelegramService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class TelegramChannel
{
    public function __construct(private TelegramService $telegramService) {}

    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        // 1. Check if user has active Telegram account
        $telegramAccount = $notifiable->telegramAccount;
        if (! $telegramAccount || ! $telegramAccount->isVerified() || ! $telegramAccount->is_active) {
            return; // Skip silently
        }

        // 2. Check if user enabled telegram notifications
        $preferences = $notifiable->notificationPreference;
        if (! $preferences || ! $preferences->telegram_enabled) {
            return; // Skip silently
        }

        $messageData = $notification->toTelegram($notifiable);
        $text = $this->formatMessage($messageData);

        try {
            $result = $this->telegramService->sendMessage($telegramAccount->chat_id, $text);

            NotificationDelivery::create([
                'notification_id' => method_exists($notification, 'id') ? $notification->id : null,
                'user_id' => $notifiable->id,
                'channel' => 'telegram',
                'status' => $result['success'] ? 'sent' : 'failed',
                'provider_message_id' => $result['response']['result']['message_id'] ?? null,
                'error_message' => $result['success'] ? null : json_encode($result['response']),
                'sent_at' => $result['success'] ? now() : null,
            ]);

        } catch (\Throwable $e) {
            Log::error('TelegramChannel: Exception while sending notification', [
                'user_id' => $notifiable->id,
                'error' => $e->getMessage(),
            ]);

            NotificationDelivery::create([
                'notification_id' => method_exists($notification, 'id') ? $notification->id : null,
                'user_id' => $notifiable->id,
                'channel' => 'telegram',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_at' => null,
            ]);
        }
    }

    private function formatMessage(array $data): string
    {
        $header = $data['header'] ?? '🔔 <b>Task Reminder</b>';
        $text = "{$header}\n\n";

        if (! empty($data['intro'])) {
            $text .= "{$data['intro']}\n\n";
        }

        if (! empty($data['task_code']) && ! empty($data['task_title'])) {
            $text .= "<b>Task:</b> {$data['task_code']} - {$data['task_title']}\n";
        } elseif (! empty($data['task_title'])) {
            $text .= "<b>Task:</b> {$data['task_title']}\n";
        }

        if (! empty($data['project_name'])) {
            $text .= "<b>Project:</b> {$data['project_name']}\n";
        }
        if (! empty($data['priority'])) {
            $text .= "<b>Priority:</b> {$data['priority']}\n";
        }
        if (! empty($data['due_date'])) {
            $text .= "<b>Due:</b> {$data['due_date']}\n";
        }
        if (! empty($data['remind_at'])) {
            $text .= "<b>Reminder Time:</b> {$data['remind_at']}\n";
        }
        if (! empty($data['assigned_by'])) {
            $text .= "<b>Assigned by:</b> {$data['assigned_by']}\n";
        }

        if (! empty($data['message'])) {
            $text .= "\n{$data['message']}\n";
        }

        if (! empty($data['task_url'])) {
            $text .= "\n<a href=\"{$data['task_url']}\">Open Task in Application</a>";
        }

        return $text;
    }
}
