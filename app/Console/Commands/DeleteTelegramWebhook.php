<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class DeleteTelegramWebhook extends Command
{
    protected $signature = 'telegram:delete-webhook';

    protected $description = 'Delete the configured Telegram bot webhook for local development polling.';

    public function handle(TelegramService $telegramService): int
    {
        $botToken = config('services.telegram.bot_token');

        if (! $botToken) {
            $this->error('TELEGRAM_BOT_TOKEN is not configured in .env');

            return self::FAILURE;
        }

        $this->info('Deleting Telegram webhook...');

        try {
            $result = $telegramService->deleteWebhook();

            if ($result['success']) {
                $this->info('Webhook deleted successfully. Telegram is ready for polling via telegram:poll.');

                return self::SUCCESS;
            }

            $this->error('Failed to delete webhook.');
            $this->line(json_encode($result['response'], JSON_PRETTY_PRINT));

            return self::FAILURE;
        } catch (\Throwable $e) {
            $msg = preg_replace('/bot[0-9]+:[A-Za-z0-9_-]+/', 'bot[REDACTED]', $e->getMessage());
            $this->error('Error deleting webhook: '.$msg);

            return self::FAILURE;
        }
    }
}
