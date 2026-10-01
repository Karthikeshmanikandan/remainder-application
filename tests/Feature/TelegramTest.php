<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\TelegramAccountService;
use App\Services\TelegramUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_verification_code(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('settings.telegram.generate'));

        $response->assertRedirect(route('settings.telegram'));

        $this->assertDatabaseHas('telegram_accounts', [
            'user_id' => $user->id,
        ]);

        $account = TelegramAccount::where('user_id', $user->id)->first();
        $this->assertNotNull($account->verification_code);
        $this->assertNotNull($account->verification_expires_at);
        $this->assertFalse($account->is_active);
    }

    public function test_telegram_update_service_handles_start_command_with_code(): void
    {
        $user = User::factory()->create();
        $accountService = app(TelegramAccountService::class);
        $code = $accountService->generateVerificationCode($user);

        Http::fake([
            'api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 123]], 200),
        ]);

        $updateService = app(TelegramUpdateService::class);
        $updateService->handleUpdate([
            'message' => [
                'text' => '/start '.$code,
                'chat' => ['id' => '123456789'],
                'from' => ['id' => '987654321', 'username' => 'testuser'],
            ],
        ]);

        $account = TelegramAccount::where('user_id', $user->id)->first();

        $this->assertTrue($account->isVerified());
        $this->assertEquals('123456789', $account->chat_id);
        $this->assertEquals('987654321', $account->telegram_user_id);
        $this->assertEquals('testuser', $account->telegram_username);
        $this->assertTrue($account->is_active);
        $this->assertNull($account->verification_code);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'telegram_enabled' => true,
        ]);
    }

    public function test_cannot_link_one_chat_id_to_multiple_users(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $accountService = app(TelegramAccountService::class);

        $code1 = $accountService->generateVerificationCode($user1);
        $code2 = $accountService->generateVerificationCode($user2);

        $linked1 = $accountService->verifyCodeAndLink($code1, 'chat-123');
        $this->assertNotNull($linked1);

        $linked2 = $accountService->verifyCodeAndLink($code2, 'chat-123');
        $this->assertNull($linked2);
    }

    public function test_user_can_unlink_account(): void
    {
        $user = User::factory()->create();
        $account = TelegramAccount::create([
            'user_id' => $user->id,
            'chat_id' => '123',
            'verified_at' => now(),
            'is_active' => true,
        ]);

        NotificationPreference::create([
            'user_id' => $user->id,
            'telegram_enabled' => true,
        ]);

        $this->actingAs($user)->post(route('settings.telegram.unlink'));

        $account->refresh();

        $this->assertNull($account->chat_id);
        $this->assertFalse($account->is_active);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'telegram_enabled' => false,
        ]);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        config(['services.telegram.webhook_secret' => 'correct-secret']);

        $response = $this->postJson('/telegram/webhook', [], [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
        ]);

        $response->assertStatus(403);
    }

    public function test_webhook_accepts_valid_secret(): void
    {
        config(['services.telegram.webhook_secret' => 'correct-secret']);

        $response = $this->postJson('/telegram/webhook', [], [
            'X-Telegram-Bot-Api-Secret-Token' => 'correct-secret',
        ]);

        $response->assertStatus(200);
    }

    public function test_user_can_update_preferences(): void
    {
        $user = User::factory()->create();

        // Cannot enable telegram if not verified
        $response = $this->actingAs($user)->post(route('settings.telegram.preferences'), [
            'in_app_enabled' => 1,
            'telegram_enabled' => 1,
        ]);

        $response->assertSessionHas('error');

        // Setup verified account
        TelegramAccount::create([
            'user_id' => $user->id,
            'chat_id' => '123',
            'verified_at' => now(),
            'is_active' => true,
        ]);

        // Now can enable
        $response = $this->actingAs($user)->post(route('settings.telegram.preferences'), [
            'in_app_enabled' => 0,
            'telegram_enabled' => 1,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'in_app_enabled' => false,
            'telegram_enabled' => true,
        ]);
    }
}
