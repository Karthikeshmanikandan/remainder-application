<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use App\Services\TelegramUpdateService;
use Illuminate\Console\Command;

class PollTelegramUpdates extends Command
{
    protected $signature = 'telegram:poll {--timeout=20 : Telegram long-polling timeout in seconds} {--once : Run once and exit (useful for testing)}';

    protected $description = 'Poll Telegram for updates in local development environment.';

    private bool $running = true;

    public function handle(TelegramService $telegramService, TelegramUpdateService $updateService): int
    {
        $botToken = config('services.telegram.bot_token');

        if (! $botToken) {
            $this->error('TELEGRAM_BOT_TOKEN is not configured in .env');

            return self::FAILURE;
        }

        $this->info('Checking Telegram bot status and webhook configuration...');

        $webhookInfo = $telegramService->getWebhookInfo();

        if (! $webhookInfo['success']) {
            $this->error('Failed to connect to Telegram API or reach Telegram server.');
            if (isset($webhookInfo['response']['error'])) {
                $this->error($webhookInfo['response']['error']);
            }

            return self::FAILURE;
        }

        $url = $webhookInfo['response']['result']['url'] ?? '';

        if (! empty($url)) {
            $this->error('Telegram webhook is currently configured (URL: '.$url.').');
            $this->warn('Telegram does not allow getUpdates polling while a webhook is active.');
            $this->info('Please run `php artisan telegram:delete-webhook` before starting polling.');

            return self::FAILURE;
        }

        $this->info('Telegram webhook is not active. Starting local development polling loop...');
        $this->comment('Press Ctrl+C to stop.');

        // Trap signals if supported (e.g. on Linux/Mac/CLI)
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () {
                $this->running = false;
                $this->info("\nStopping Telegram polling...");
            });
            pcntl_signal(SIGTERM, function () {
                $this->running = false;
                $this->info("\nStopping Telegram polling...");
            });
        }

        $offset = 0;
        $timeout = (int) $this->option('timeout');
        $runOnce = (bool) $this->option('once');

        while ($this->running) {
            try {
                $response = $telegramService->getUpdates($offset, $timeout);

                if (! $response['success']) {
                    $this->warn('Error fetching updates from Telegram: '.(json_encode($response['response'] ?? [])));
                    if ($runOnce) {
                        break;
                    }
                    sleep(2);

                    continue;
                }

                $updates = $response['response']['result'] ?? [];

                foreach ($updates as $update) {
                    $updateId = $update['update_id'] ?? null;
                    if ($updateId !== null) {
                        $offset = $updateId + 1;
                    }

                    $fromUser = $update['message']['from']['username'] ?? $update['message']['from']['first_name'] ?? 'Unknown';
                    $text = $update['message']['text'] ?? '[non-text message]';

                    $this->line(sprintf(' <fg=gray>[%s]</> Received update #%d from @%s: <fg=cyan>%s</>', now()->format('H:i:s'), $updateId, $fromUser, $text));

                    try {
                        $updateService->handleUpdate($update);
                        $this->line(sprintf(' <fg=gray>[%s]</> <fg=green>✓ Processed update #%d</>', now()->format('H:i:s'), $updateId));
                    } catch (\Throwable $e) {
                        $this->error(sprintf('Error processing update #%d: %s', $updateId, $e->getMessage()));
                    }
                }

                if ($runOnce) {
                    break;
                }
            } catch (\Throwable $e) {
                $msg = preg_replace('/bot[0-9]+:[A-Za-z0-9_-]+/', 'bot[REDACTED]', $e->getMessage());
                $this->error('Unexpected error in polling loop: '.$msg);
                if ($runOnce) {
                    break;
                }
                sleep(2);
            }
        }

        $this->info('Telegram polling finished.');

        return self::SUCCESS;
    }
}
