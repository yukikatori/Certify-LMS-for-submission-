<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知の認可ルール。
 * 本人の通知のみ既読にできる。
 */
class NotificationPolicy
{
    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $this->belongsToUser($user, $notification);
    }

    public function markAsRead(User $user, DatabaseNotification $notification): bool
    {
        return $this->belongsToUser($user, $notification);
    }

    private function belongsToUser(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (string) $notification->notifiable_id === (string) $user->getKey();
    }
}
