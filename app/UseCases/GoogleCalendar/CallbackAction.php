<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Models\GoogleCalendarConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Google Calendar 連携のユースケース。
 * Google から戻ってきた認可結果を検証して、トークンをDBに保存する。
 */
class CallbackAction
{
    public function __invoke(Request $request): RedirectResponse
    {
        $redirectPath = $request->session()->pull(
            'google_calendar_redirect_path',
            '/settings/availability'
        );

        $expectedState = $request->session()->pull('google_calendar_oauth_state');
        $actualState = $request->query('state');

        if (! $expectedState || ! $actualState || ! hash_equals($expectedState, $actualState)) {
            return redirect($redirectPath)
                ->with('error', 'Googleカレンダー連携の検証に失敗しました。もう一度お試しください。');
        }

        if ($request->filled('error')) {
            return redirect($redirectPath)
                ->with('error', 'Googleカレンダー連携がキャンセルされました。');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return redirect($redirectPath)
                ->with('error', 'Googleカレンダー連携に必要な認可コードを取得できませんでした。');
        }

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'redirect_uri' => config('services.google_calendar.redirect_uri'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        if ($tokenResponse->failed()) {
            report(new \RuntimeException('Google OAuth token exchange failed: '.$tokenResponse->body()));

            return redirect($redirectPath)
                ->with('error', 'Googleカレンダー連携に失敗しました。時間をおいて再度お試しください。');
        }

        $token = $tokenResponse->json();

        $accessToken = $token['access_token'] ?? null;
        $refreshToken = $token['refresh_token'] ?? null;
        $expiresIn = $token['expires_in'] ?? null;

        if (! is_string($accessToken)) {
            return redirect($redirectPath)
                ->with('error', 'Googleカレンダー連携に必要なトークンを取得できませんでした。');
        }

        $googleAccountId = null;
        $googleEmail = null;

        $userInfoResponse = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if ($userInfoResponse->successful()) {
            $userInfo = $userInfoResponse->json();

            $googleAccountId = $userInfo['id'] ?? null;
            $googleEmail = $userInfo['email'] ?? null;
        }

        $values = [
            'google_account_id' => $googleAccountId,
            'google_email' => $googleEmail,
            'access_token' => $accessToken,
            'token_expires_at' => is_numeric($expiresIn)
                ? CarbonImmutable::now()->addSeconds((int) $expiresIn)
                : null,
            'connected_at' => now(),
        ];

        if (is_string($refreshToken) && $refreshToken !== '') {
            $values['refresh_token'] = $refreshToken;
        }

        GoogleCalendarConnection::updateOrCreate(
            ['coach_id' => $request->user()->id],
            $values
        );

        return redirect($redirectPath)
            ->with('success', 'Googleカレンダーと連携しました。');
    }
}