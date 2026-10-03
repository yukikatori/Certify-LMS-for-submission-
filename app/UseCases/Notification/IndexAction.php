<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 通知一覧を取得するユースケース。
 * 既読、未読で絞り込む。
 */
final class IndexAction
{
    public function __invoke(User $user, string $tab): LengthAwarePaginator
    {
        $query = $tab === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        return $query
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }
}
