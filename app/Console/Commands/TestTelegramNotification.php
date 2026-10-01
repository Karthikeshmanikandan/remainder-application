<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class TestTelegramNotification extends Command
{
    protected $signature = 'telegram:test {email : The email of the user to test}';

    protected $description = 'Send a test notification to a specific user via Telegram.';

    public function handle(TelegramService $telegramService): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found.");

            return self::FAILURE;
        }

        $account = $user->telegramAccount;

        if (! $account || ! $account->isVerified() || ! $account->is_active) {
            $this->error('User does not have an active verified Telegram account.');

            return self::FAILURE;
        }

        $this->info("Sending test message to {$user->name}...");

        try {
            $result = $telegramService->sendMessage($account->chat_id, '🧪 This is a test message from the Task Reminder Application.');

            if ($result['success']) {
                $this->info('Message sent successfully.');

                return self::SUCCESS;
            }

            $this->error('Failed to send message.');
            $this->line(json_encode($result['response'], JSON_PRETTY_PRINT));

            return self::FAILURE;
        } catch (\Throwable $e) {
            $msg = preg_replace('/bot[0-9]+:[A-Za-z0-9_-]+/', 'bot[REDACTED]', $e->getMessage());
            $this->error('Error sending message: '.$msg);

            return self::FAILURE;
        }
    }
}
