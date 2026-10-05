<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Google Calendar 連携のユースケース。
 * ログイン中コーチの google_calendar_connections レコードを削除する。
 */
class DestroyAction
{
    public function __invoke(User $coach): void
    {
        $connection = $coach->googleCredential;

        if (! $connection) {
            return;
        }

        try {
            $token = $connection->refresh_token ?: $connection->access_token;

            if (is_string($token) && $token !== '') {
                $response = Http::asForm()->post('https://oauth2.googleapis.com/revoke', [
                    'token' => $token,
                ]);

                if ($response->failed()) {
                    report(new \RuntimeException('Google OAuth token revoke failed: '.$response->body()));
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $connection->delete();
    }
}