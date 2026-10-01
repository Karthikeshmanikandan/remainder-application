<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private ?string $botToken;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
    }

    /**
     * Get the configured bot username.
     */
    public function getBotUsername(): string
    {
        return config('services.telegram.bot_username') ?: 'MyCompanyBot';
    }

    /**
     * Generate a Telegram bot deep-link URL for one-time linking or actions.
     */
    public function getDeepLink(string $payload): string
    {
        $botUsername = $this->getBotUsername();

        return "https://t.me/{$botUsername}?start={$payload}";
    }

    private function getBaseUrl(): ?string
    {
        if (! $this->botToken) {
            return null;
        }

        return "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Get a configured HTTP client request with proper CA bundle verification.
     */
    private function newClient(int $timeout = 10, int $connectTimeout = 5)
    {
        $client = Http::timeout($timeout)->connectTimeout($connectTimeout);

        $caBundle = ini_get('curl.cainfo') ?: ini_get('openssl.cafile') ?: getenv('CURL_CA_BUNDLE') ?: getenv('SSL_CERT_FILE');
        if (! $caBundle) {
            $userProfile = getenv('USERPROFILE') ?: getenv('HOME');
            if ($userProfile && file_exists($userProfile.'/.cacert/cacert.pem')) {
                $caBundle = $userProfile.'/.cacert/cacert.pem';
            }
        }

        if ($caBundle && file_exists($caBundle)) {
            $client = $client->withOptions(['verify' => $caBundle]);
        }

        return $client;
    }

    /**
     * Send a message to a specific chat ID.
     */
    public function sendMessage(string $chatId, string $text, array $options = []): array
    {
        $baseUrl = $this->getBaseUrl();
        if (! $baseUrl) {
            Log::warning('TelegramService: Cannot send message because TELEGRAM_BOT_TOKEN is not configured.');

            return [
                'success' => false,
                'status' => 500,
                'response' => ['error' => 'TELEGRAM_BOT_TOKEN is not configured'],
            ];
        }

        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options);

        $response = $this->newClient(10, 5)
            ->post($baseUrl.'/sendMessage', $payload);

        if (! $response->successful()) {
            Log::error('TelegramService: Failed to send message.', [
                'status' => $response->status(),
                'error' => $response->body(),
                'chat_id' => $chatId,
            ]);
        }

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json() ?? ['raw_body' => $response->body()],
        ];
    }

    /**
     * Set the webhook URL for the bot.
     */
    public function setWebhook(string $url, string $secretToken): array
    {
        $response = $this->newClient(10, 5)
            ->post($this->getBaseUrl().'/setWebhook', [
                'url' => $url,
                'secret_token' => $secretToken,
                'drop_pending_updates' => true,
            ]);

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json(),
        ];
    }

    /**
     * Get webhook info to check if a webhook is currently active.
     */
    public function getWebhookInfo(): array
    {
        $baseUrl = $this->getBaseUrl();
        if (! $baseUrl) {
            return [
                'success' => false,
                'status' => 500,
                'response' => ['error' => 'TELEGRAM_BOT_TOKEN is not configured'],
            ];
        }

        $response = $this->newClient(10, 5)->get($baseUrl.'/getWebhookInfo');

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json() ?? ['raw_body' => $response->body()],
        ];
    }

    /**
     * Get updates from Telegram using long polling.
     */
    public function getUpdates(int $offset = 0, int $timeout = 30): array
    {
        $baseUrl = $this->getBaseUrl();
        if (! $baseUrl) {
            return [
                'success' => false,
                'status' => 500,
                'response' => ['error' => 'TELEGRAM_BOT_TOKEN is not configured'],
            ];
        }

        $params = [
            'offset' => $offset,
            'timeout' => $timeout,
        ];

        // HTTP timeout slightly larger than Telegram long-polling timeout
        $response = $this->newClient($timeout + 5, 5)
            ->get($baseUrl.'/getUpdates', $params);

        if (! $response->successful()) {
            Log::error('TelegramService: Failed to get updates.', [
                'status' => $response->status(),
                'error' => $response->body(),
            ]);
        }

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json() ?? ['raw_body' => $response->body()],
        ];
    }

    /**
     * Delete the webhook.
     */
    public function deleteWebhook(): array
    {
        $baseUrl = $this->getBaseUrl();
        if (! $baseUrl) {
            return [
                'success' => false,
                'status' => 500,
                'response' => ['error' => 'TELEGRAM_BOT_TOKEN is not configured'],
            ];
        }

        $response = $this->newClient(10, 5)->post($baseUrl.'/deleteWebhook');

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json() ?? ['raw_body' => $response->body()],
        ];
    }
}
