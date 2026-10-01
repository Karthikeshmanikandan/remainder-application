<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Set the Telegram bot webhook URL.';

    public function handle(TelegramService $telegramService): int
    {
        $appUrl = config('app.url');
        $botToken = config('services.telegram.bot_token');
        $secretToken = config('services.telegram.webhook_secret');

        if (! $botToken) {
            $this->error('TELEGRAM_BOT_TOKEN is not configured in .env');

            return self::FAILURE;
        }

        if (! $secretToken) {
            $this->error('TELEGRAM_WEBHOOK_SECRET is not configured in .env');

            return self::FAILURE;
        }

        if (str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            $this->warn('Warning: APP_URL is localhost. Telegram cannot reach a local URL.');
            $this->warn('You need a publicly accessible HTTPS URL (e.g. via ngrok) for webhooks to work.');
        }

        $webhookUrl = rtrim($appUrl, '/').'/telegram/webhook';

        $this->info("Setting webhook to: {$webhookUrl}");

        try {
            $result = $telegramService->setWebhook($webhookUrl, $secretToken);

            if ($result['success']) {
                $this->info('Webhook set successfully.');

                return self::SUCCESS;
            }

            $this->error('Failed to set webhook.');
            $this->line(json_encode($result['response'], JSON_PRETTY_PRINT));

            return self::FAILURE;
        } catch (\Throwable $e) {
            $msg = preg_replace('/bot[0-9]+:[A-Za-z0-9_-]+/', 'bot[REDACTED]', $e->getMessage());
            $this->error('Error setting webhook: '.$msg);

            return self::FAILURE;
        }
    }
}
