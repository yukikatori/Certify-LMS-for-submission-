<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api\V1\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_fetch_notifications(): void
    {
        $this->getJson(route('api.v1.notifications.index'))->assertUnauthorized();
    }

    public function test_user_can_fetch_only_own_notifications_as_json(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $older = $this->createNotificationFor($user, [
            'title' => '古い通知',
            'message' => '古い通知本文',
        ], read: true, createdAt: now()->subDay());
        $latest = $this->createNotificationFor($user, [
            'title' => '最新通知',
            'body_preview' => 'プレビュー本文',
            'action_url' => route('dashboard.index'),
        ], createdAt: now());
        $otherNotification = $this->createNotificationFor($other, [
            'title' => '他人宛の通知',
        ], createdAt: now()->addMinute());

        $response = $this->actingAs($user)->getJson(route('api.v1.notifications.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'notifications');
        $response->assertJsonPath('unread_count', 1);
        $response->assertJsonPath('notifications.0.id', $latest->id);
        $response->assertJsonPath('notifications.0.title', '最新通知');
        $response->assertJsonPath('notifications.0.message', 'プレビュー本文');
        $response->assertJsonPath('notifications.0.action_url', route('dashboard.index'));
        $response->assertJsonPath('notifications.0.is_unread', true);
        $response->assertJsonPath('notifications.0.read_at', null);
        $response->assertJsonPath('notifications.1.id', $older->id);
        $response->assertJsonPath('notifications.1.is_unread', false);
        $response->assertJsonMissing(['id' => $otherNotification->id]);
        $response->assertJsonMissing(['title' => '他人宛の通知']);
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotificationFor($user, [
            'action_url' => route('dashboard.index'),
        ]);
        $remainingUnread = $this->createNotificationFor($user, [
            'title' => '残る未読通知',
        ]);

        $response = $this->actingAs($user)->postJson(route('api.v1.notifications.markAsRead', $notification));

        $response->assertOk();
        $response->assertJsonPath('redirect_url', route('dashboard.index'));
        $response->assertJsonPath('unread_count', 1);
        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => $remainingUnread->id,
            'read_at' => null,
        ]);
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotificationFor($other);

        $response = $this->actingAs($user)->postJson(route('api.v1.notifications.markAsRead', $notification));

        $response->assertForbidden();
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_user_can_mark_all_own_notifications_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $first = $this->createNotificationFor($user, ['title' => '未読通知1']);
        $second = $this->createNotificationFor($user, ['title' => '未読通知2']);
        $readNotification = $this->createNotificationFor($user, ['title' => '既読通知'], read: true);
        $readAt = $readNotification->read_at;
        $otherNotification = $this->createNotificationFor($other, ['title' => '他人の未読通知']);

        $response = $this->actingAs($user)->postJson(route('api.v1.notifications.markAllAsRead'));

        $response->assertOk();
        $response->assertJsonPath('unread_count', 0);
        $this->assertDatabaseMissing('notifications', [
            'id' => $first->id,
            'read_at' => null,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'id' => $second->id,
            'read_at' => null,
        ]);
        $this->assertTrue($readNotification->fresh()->read_at->equalTo($readAt));
        $this->assertDatabaseHas('notifications', [
            'id' => $otherNotification->id,
            'read_at' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createNotificationFor(
        User $user,
        array $overrides = [],
        bool $read = false,
        ?\DateTimeInterface $createdAt = null,
    ): DatabaseNotification {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => array_merge([
                'notification_type' => 'chat_message_received',
                'title' => 'テスト通知',
                'message' => 'テスト通知です。',
                'action_url' => route('notifications.index'),
            ], $overrides),
            'read_at' => $read ? now() : null,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);

        return $notification;
    }
}
