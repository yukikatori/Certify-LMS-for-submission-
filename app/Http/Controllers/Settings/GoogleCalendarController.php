<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\UseCases\GoogleCalendar\CallbackAction;
use App\UseCases\GoogleCalendar\ConnectAction;
use App\UseCases\GoogleCalendar\DestroyAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Google Calendar 連携 controller。
 * コーチが自分の Google アカウントを LMS と任意連携する。
 */
class GoogleCalendarController extends Controller
{
    public function connect(Request $request, ConnectAction $action): RedirectResponse
    {
        return $action(
            $request,
            $request->query('redirect_path', '/settings/availability')
        );
    }

    public function callback(Request $request, CallbackAction $action): RedirectResponse
    {
        return $action($request);
    }

    public function destroy(Request $request, DestroyAction $action): RedirectResponse
    {
        $action($request->user());

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Googleカレンダー連携を解除しました。');
    }
}
