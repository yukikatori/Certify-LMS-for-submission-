<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\BusinessEventNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class BusinessEventNotificationTest extends TestCase
{
    public function test_via_returns_database_and_mail_channels(): void
    {
        $user = User::factory()->make();
        $notification = new BusinessEventNotification($this->data());

        $channels = $notification->via($user);

        $this->assertSame(['database', 'mail'], $channels);
    }

    public function test_to_array_returns_given_data(): void
    {
        $data = $this->data();
        $user = User::factory()->make();
        $notification = new BusinessEventNotification($data);

        $array = $notification->toArray($user);

        $this->assertSame($data, $array);
    }

    public function test_to_mail_builds_subject_and_action_from_data(): void
    {
        $user = User::factory()->make(['name' => '受講生太郎']);
        $notification = new BusinessEventNotification($this->data([
            'title' => 'chat に新着メッセージがあります',
            'message' => 'コーチからメッセージが届きました。',
            'action_url' => 'https://example.test/chat-rooms/room-1',
        ]));

        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('chat に新着メッセージがあります', $mail->subject);
        $this->assertSame('受講生太郎さん', $mail->greeting);
        $this->assertContains('コーチからメッセージが届きました。', $mail->introLines);
        $this->assertSame('詳細を確認する', $mail->actionText);
        $this->assertSame('https://example.test/chat-rooms/room-1', $mail->actionUrl);
    }

    public function test_notification_implements_should_queue_and_uses_notifications_queue(): void
    {
        $notification = new BusinessEventNotification($this->data());

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame('notifications', $notification->queue);
        $this->assertSame(3, $notification->tries);
        $this->assertSame([10, 60, 300], $notification->backoff());
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function data(array $overrides = []): array
    {
        return array_merge([
            'notification_type' => 'chat_message_received',
            'title' => 'テスト通知',
            'message' => 'テスト通知です。',
            'action_url' => 'https://example.test/notifications',
            'related_type' => 'chat_message',
            'related_id' => '01HN0000000000000000000000',
        ], $overrides);
    }
}
