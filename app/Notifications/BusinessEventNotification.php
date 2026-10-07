<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BusinessEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    /**
     * @param array{
     *     notification_type: string,
     *     title: string,
     *     message: string,
     *     action_url?: string,
     *     body?: string,
     *     body_preview?: string,
     *     related_type?: string,
     *     related_id?: string
     * } $data
     */
    public function __construct(private readonly array $data)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->data;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->data['title'])
            ->greeting($notifiable->name.'さん')
            ->line($this->data['message']);

        if (! empty($this->data['body'])) {
            $mail->line($this->data['body']);
        }

        if (! empty($this->data['action_url'])) {
            $mail->action('詳細を確認する', $this->data['action_url']);
        }

        return $mail->line('このメールは Certify LMS から自動送信されています。');
    }
}
