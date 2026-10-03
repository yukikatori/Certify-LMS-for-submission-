<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;

/**
 * 通知をすべて既読するユースケース。
 */
final class MarkAllAsReadAction
{
    public function __invoke(User $user): void
    {
        $user->unreadNotifications()->update([
            'read_at' => now(),
        ]);
    }
}
