<?php

namespace Tests\Feature;

use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\TelegramAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramPollingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => 'test-bot-token']);
    }

    public function test_delete_webhook_command(): void
    {
        Http::fake([
            'api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
                'description' => 'Webhook was deleted',
            ], 200),
        ]);

        $this->artisan('telegram:delete-webhook')
            ->assertSuccessful()
            ->expectsOutputToContain('Webhook deleted successfully');
    }

    public function test_polling_command_fails_if_webhook_is_active(): void
    {
        Http::fake([
            'api.telegram.org/bot*/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://example.com/telegram/webhook',
                    'has_custom_certificate' => false,
                    'pending_update_count' => 0,
                ],
            ], 200),
        ]);

        $this->artisan('telegram:poll')
            ->assertFailed()
            ->expectsOutputToContain('Telegram webhook is currently configured')
            ->expectsOutputToContain('php artisan telegram:delete-webhook');
    }

    public function test_polling_command_processes_updates_successfully_and_verifies_account(): void
    {
        $user = User::factory()->create();
        $accountService = app(TelegramAccountService::class);
        $code = $accountService->generateVerificationCode($user);

        Http::fake([
            'api.telegram.org/bot*/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => '',
                    'pending_update_count' => 0,
                ],
            ], 200),
            'api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 10001,
                        'message' => [
                            'message_id' => 456,
                            'from' => [
                                'id' => 9876543,
                                'username' => 'testemployee',
                            ],
                            'chat' => [
                                'id' => 1234567,
                            ],
                            'text' => '/start '.$code,
                        ],
                    ],
                ],
            ], 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 789],
            ], 200),
        ]);

        $this->artisan('telegram:poll', ['--once' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Received update #10001')
            ->expectsOutputToContain('Processed update #10001');

        $account = TelegramAccount::where('user_id', $user->id)->first();
        $this->assertNotNull($account);
        $this->assertTrue($account->isVerified());
        $this->assertEquals('1234567', $account->chat_id);
        $this->assertEquals('testemployee', $account->telegram_username);
        $this->assertTrue($account->is_active);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'telegram_enabled' => true,
        ]);
    }

    public function test_polling_command_handles_api_failure_gracefully(): void
    {
        Http::fake([
            'api.telegram.org/bot*/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => '',
                ],
            ], 200),
            'api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => false,
                'description' => 'Unauthorized',
            ], 401),
        ]);

        $this->artisan('telegram:poll', ['--once' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Error fetching updates from Telegram');
    }
}
