<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_notifications_index(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_own_notifications_index(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotificationFor($user, [
            'title' => '自分宛の通知',
            'message' => '自分宛の通知本文',
        ]);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewIs('notifications.index');
        $response->assertViewHas('notifications', fn ($notifications) => $notifications
            ->getCollection()
            ->contains('id', $notification->id));
        $response->assertViewHas('unreadCount', 1);
        $response->assertViewHas('tab', 'all');
        $response->assertSee($notification->data['title']);
        $response->assertSee($notification->data['message']);
    }

    public function test_index_does_not_show_other_users_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();

        $ownNotification = $this->createNotificationFor($user, ['title' => '自分宛の通知']);
        $otherNotification = $this->createNotificationFor($other, ['title' => '他人宛の通知']);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewIs('notifications.index');
        $response->assertViewHas('notifications', fn ($notifications) => $notifications
            ->getCollection()
            ->contains('id', $ownNotification->id)
            && ! $notifications->getCollection()->contains('id', $otherNotification->id));
        $response->assertSee('自分宛の通知');
        $response->assertDontSee('他人宛の通知');
    }

    public function test_unread_tab_shows_only_unread_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        $unreadNotification = $this->createNotificationFor($user, ['title' => '未読通知']);
        $readNotification = $this->createNotificationFor($user, ['title' => '既読通知'], read: true);

        $response = $this->actingAs($user)->get(route('notifications.index', ['tab' => 'unread']));

        $response->assertOk();
        $response->assertViewIs('notifications.index');
        $response->assertViewHas('notifications', fn ($notifications) => $notifications
            ->getCollection()
            ->contains('id', $unreadNotification->id)
            && ! $notifications->getCollection()->contains('id', $readNotification->id));
        $response->assertViewHas('unreadCount', 1);
        $response->assertViewHas('tab', 'unread');
        $response->assertSee('未読通知');
        $response->assertDontSee('既読通知');
    }

    public function test_user_can_view_own_notification_detail_and_it_marks_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotificationFor($user, [
            'title' => '詳細表示する通知',
            'body' => '詳細画面で表示する本文です。',
        ]);

        $response = $this->actingAs($user)->get(route('notifications.show', $notification));

        $response->assertOk();
        $response->assertViewIs('notifications.show');
        $response->assertViewHas('notification', fn ($viewNotification) => $viewNotification->id === $notification->id);
        $response->assertSee('詳細表示する通知');
        $response->assertSee('詳細画面で表示する本文です。');
        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_user_cannot_view_other_users_notification_detail(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotificationFor($other);

        $response = $this->actingAs($user)->get(route('notifications.show', $notification));

        $response->assertForbidden();
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_user_can_mark_own_notification_as_read_and_redirect_to_action_url(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotificationFor($user, [
            'action_url' => route('dashboard.index'),
        ]);

        $response = $this->actingAs($user)->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect(route('dashboard.index'));
        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotificationFor($other);

        $response = $this->actingAs($user)->post(route('notifications.markAsRead', $notification));

        $response->assertForbidden();
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_user_can_mark_all_own_notifications_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $first = $this->createNotificationFor($user, ['title' => '未読通知1']);
        $second = $this->createNotificationFor($user, ['title' => '未読通知2']);

        $response = $this->actingAs($user)->post(route('notifications.markAllAsRead'));

        $response->assertRedirect(route('notifications.index'));
        $this->assertDatabaseMissing('notifications', [
            'id' => $first->id,
            'read_at' => null,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'id' => $second->id,
            'read_at' => null,
        ]);
    }

    public function test_mark_all_marks_only_unread_own_notifications_and_keeps_read_notifications_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $unreadNotification = $this->createNotificationFor($user, ['title' => '未読通知']);
        $readNotification = $this->createNotificationFor($user, ['title' => '既読通知'], read: true);
        $readAt = $readNotification->read_at;

        $response = $this->actingAs($user)->post(route('notifications.markAllAsRead'));

        $response->assertRedirect(route('notifications.index'));
        $this->assertDatabaseMissing('notifications', [
            'id' => $unreadNotification->id,
            'read_at' => null,
        ]);
        $this->assertTrue($readNotification->fresh()->read_at->equalTo($readAt));
    }

    public function test_mark_all_does_not_mark_other_users_notifications_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $ownNotification = $this->createNotificationFor($user);
        $otherNotification = $this->createNotificationFor($other);

        $response = $this->actingAs($user)->post(route('notifications.markAllAsRead'));

        $response->assertRedirect(route('notifications.index'));
        $this->assertDatabaseMissing('notifications', [
            'id' => $ownNotification->id,
            'read_at' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => $otherNotification->id,
            'read_at' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createNotificationFor(User $user, array $overrides = [], bool $read = false): DatabaseNotification
    {
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
        ]);

        return $notification;
    }
}
