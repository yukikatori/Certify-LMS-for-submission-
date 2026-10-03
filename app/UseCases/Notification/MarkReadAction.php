<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

/**
 * 通知を既読するユースケース。
 */
final class MarkReadAction
{
    public function __invoke(DatabaseNotification $notification): ?string
    {
        DB::transaction(function () use ($notification): void {
            $notification->markAsRead();
        });

        $data = is_array($notification->data) ? $notification->data : [];

        return $data['action_url'] ?? null;
    }
}
