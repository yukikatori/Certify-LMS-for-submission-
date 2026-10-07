<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\UseCases\Api\V1\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkReadAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知機能の Controller。
 * 認証済みユーザーへの通知一覧画面と既読化の動線。
 */
class NotificationController extends Controller
{
    public function index(Request $request, IndexAction $action): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'notifications' => $action($user),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(
        Request $request,
        MarkAllAsReadAction $action,
    ): JsonResponse {
        $user = $request->user();

        $action($user);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(
        Request $request,
        DatabaseNotification $notification,
        MarkReadAction $action,
    ): JsonResponse {
        $this->authorize('markAsRead', $notification);

        $redirectUrl = $action($notification);

        return response()->json([
            'redirect_url' => $redirectUrl ?? route('notifications.index'),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
