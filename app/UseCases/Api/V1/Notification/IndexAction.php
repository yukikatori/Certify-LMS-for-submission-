<?php

declare(strict_types=1);

namespace App\UseCases\Api\V1\Notification;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * 通知一覧を取得するユースケース。
 */
final class IndexAction
{
    public function __invoke(User $user): Collection
    {
        return $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->serializeNotification($notification))
            ->values();
    }

    private function serializeNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'type' => $data['notification_type'] ?? null,
            'title' => $data['title'] ?? '通知',
            'message' => $data['body_preview']
                ?? $data['message']
                ?? $data['body']
                ?? '',
            'action_url' => $data['action_url'] ?? route('notifications.show', $notification),
            'read_at' => $notification->read_at?->toISOString(),
            'is_unread' => $notification->read_at === null,
            'created_at' => $notification->created_at?->toISOString(),
            'created_relative' => $notification->created_at?->diffForHumans(),
        ];
    }
}
