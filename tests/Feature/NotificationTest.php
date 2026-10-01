<?php

namespace Tests\Feature;

use App\Models\TaskReminder;
use App\Models\User;
use App\Services\TaskReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // 1. User can view own notifications
    // -------------------------------------------------------------------------
    public function test_user_can_view_own_notifications(): void
    {
        $user = User::factory()->create();
        $reminder = TaskReminder::factory()->due()->create(['user_id' => $user->id]);

        // Process without faking — let real DB notification be persisted
        app(TaskReminderService::class)->processDueReminders();

        $response = $this->actingAs($user)->get(route('notifications.index'));
        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
    }

    // -------------------------------------------------------------------------
    // 2. User cannot access another user's notification
    // -------------------------------------------------------------------------
    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $reminder = TaskReminder::factory()->due()->create(['user_id' => $owner->id]);

        app(TaskReminderService::class)->processDueReminders();

        $notification = $owner->notifications()->first();

        $this->actingAs($other)
            ->post(route('notifications.read', $notification->id))
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // 3. User can mark own notification as read
    // -------------------------------------------------------------------------
    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $reminder = TaskReminder::factory()->due()->create(['user_id' => $user->id]);

        app(TaskReminderService::class)->processDueReminders();

        $notification = $user->notifications()->first();
        $this->assertNull($notification->read_at);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    // -------------------------------------------------------------------------
    // 4. Unread count is correct
    // -------------------------------------------------------------------------
    public function test_unread_count_is_correct(): void
    {
        $user = User::factory()->create();

        // Create 3 due reminders for this user
        TaskReminder::factory()->due()->count(3)->create(['user_id' => $user->id]);
        app(TaskReminderService::class)->processDueReminders();

        $this->assertEquals(3, $user->unreadNotifications()->count());

        // Mark one as read
        $user->notifications()->first()->markAsRead();

        $this->assertEquals(2, $user->fresh()->unreadNotifications()->count());
    }

    // -------------------------------------------------------------------------
    // 5. Mark all as read clears unread count
    // -------------------------------------------------------------------------
    public function test_mark_all_as_read_works(): void
    {
        $user = User::factory()->create();
        TaskReminder::factory()->due()->count(2)->create(['user_id' => $user->id]);
        app(TaskReminderService::class)->processDueReminders();

        $this->assertEquals(2, $user->unreadNotifications()->count());

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    // -------------------------------------------------------------------------
    // 6. Guest cannot access notifications
    // -------------------------------------------------------------------------
    public function test_guest_cannot_access_notifications(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }
}
