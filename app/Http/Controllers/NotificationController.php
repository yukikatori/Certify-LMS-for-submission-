<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkReadAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * 通知機能の Controller。
 * 認証済みユーザーへの通知一覧画面と既読化の動線。
 */
class NotificationController extends Controller
{
    public function index(Request $request, IndexAction $action): View
    {
        $tab = $request->query('tab') === 'unread' ? 'unread' : 'all';

        $notifications = $action(
            user: $request->user(),
            tab: $tab,
        );

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
            'tab' => $tab,
        ]);
    }

    public function markAsRead(
        DatabaseNotification $notification,
        MarkReadAction $action,
    ): RedirectResponse {
        $this->authorize('markAsRead', $notification);

        $url = $action($notification);

        return $url
            ? redirect()->to($url)
            : redirect()->route('notifications.index');
    }

    public function show(
        DatabaseNotification $notification,
        MarkReadAction $action,
    ): View {
        $this->authorize('view', $notification);

        $action($notification);

        return view('notifications.show', [
            'notification' => $notification->fresh(),
        ]);
    }

    public function markAllAsRead(
        Request $request,
        MarkAllAsReadAction $action,
    ): RedirectResponse {
        $action($request->user());

        return redirect()
            ->route('notifications.index')
            ->with('success', '通知を既読にしました。');
    }
}
