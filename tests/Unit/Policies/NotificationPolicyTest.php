<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\NotificationPolicy;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationPolicyTest extends TestCase
{
    public function test_user_can_view_own_notification(): void
    {
        $user = $this->makeUser();
        $notification = $this->createNotificationFor($user);

        $this->assertTrue((new NotificationPolicy)->view($user, $notification));
    }

    public function test_user_cannot_view_other_users_notification(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $notification = $this->createNotificationFor($other);

        $this->assertFalse((new NotificationPolicy)->view($user, $notification));
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = $this->makeUser();
        $notification = $this->createNotificationFor($user);

        $this->assertTrue((new NotificationPolicy)->markAsRead($user, $notification));
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $notification = $this->createNotificationFor($other);

        $this->assertFalse((new NotificationPolicy)->markAsRead($user, $notification));
    }

    private function makeUser(): User
    {
        return User::factory()->student()->inProgress()->make([
            'id' => (string) Str::ulid(),
        ]);
    }

    private function createNotificationFor(User $user): DatabaseNotification
    {
        return (new DatabaseNotification)->forceFill([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => 'テスト通知',
                'message' => 'テスト通知です。',
            ],
        ]);
    }
}
