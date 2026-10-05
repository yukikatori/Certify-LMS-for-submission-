<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Google Calendar 連携のユースケース。
 * state を session に保存する。
 */
class ConnectAction
{
    public function __invoke(Request $request, string $redirectPath): RedirectResponse
    {
        $state = Str::random(40);
        $redirectPath = $this->safeRedirectPath($redirectPath);

        $request->session()->put('google_calendar_oauth_state', $state);
        $request->session()->put('google_calendar_redirect_path', $redirectPath);

        $query = http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => config('services.google_calendar.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/calendar',
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
            ]),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    private function safeRedirectPath(string $redirectPath): string
    {
        $allowedPaths = [
            '/settings/availability',
        ];

        if (in_array($redirectPath, $allowedPaths, true)) {
            return $redirectPath;
        }

        return '/settings/availability';
    }
}