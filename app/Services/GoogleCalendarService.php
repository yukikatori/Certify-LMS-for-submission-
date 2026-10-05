<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCalendarConnection;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/**
 * Google Calendar API 呼び出しを共通化する Service
 */
class GoogleCalendarService
{
    /**
     * access token が期限切れなら refresh token で更新する
     */
    public function refreshAccessTokenIfNeeded(GoogleCalendarConnection $connection): GoogleCalendarConnection
    {
        if (
            $connection->token_expires_at
            && $connection->token_expires_at->isFuture()
        ) {
            return $connection;
        }

        if (! $connection->refresh_token) {
            throw new \RuntimeException('Google refresh token is missing.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Google OAuth token refresh failed: '.$response->body());
        }

        $token = $response->json();

        $accessToken = $token['access_token'] ?? null;
        $expiresIn = $token['expires_in'] ?? null;

        if (! is_string($accessToken)) {
            throw new \RuntimeException('Google access token was not returned.');
        }

        $connection->update([
            'access_token' => $accessToken,
            'token_expires_at' => is_numeric($expiresIn)
                ? CarbonImmutable::now()->addSeconds((int) $expiresIn)
                : null,
        ]);

        return $connection->refresh();
    }

    /**
     * Google Calendar の予定あり時間を取得する
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function fetchBusyPeriods(
        GoogleCalendarConnection $connection,
        CarbonInterface $timeMin,
        CarbonInterface $timeMax,
    ): array {
        $connection = $this->refreshAccessTokenIfNeeded($connection);

        $response = Http::withToken($connection->access_token)
            ->post('https://www.googleapis.com/calendar/v3/freeBusy', [
                'timeMin' => $timeMin->toIso8601String(),
                'timeMax' => $timeMax->toIso8601String(),
                'items' => [
                    ['id' => 'primary'],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Google Calendar freeBusy failed: '.$response->body());
        }

        $busyItems = $response->json('calendars.primary.busy', []);

        return collect($busyItems)
            ->map(fn (array $busy): array => [
                'start' => CarbonImmutable::parse($busy['start']),
                'end' => CarbonImmutable::parse($busy['end']),
            ])
            ->all();
    }

    /**
     * Google Calendar にイベントを作成する
     */
    public function createEvent(
        GoogleCalendarConnection $connection,
        string $summary,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $description = null,
        ?string $location = null,
    ): string {
        $connection = $this->refreshAccessTokenIfNeeded($connection);

        $response = Http::withToken($connection->access_token)
            ->post('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
                'summary' => $summary,
                'description' => $description,
                'location' => $location,
                'start' => [
                    'dateTime' => $startsAt->toIso8601String(),
                    'timeZone' => config('app.timezone'),
                ],
                'end' => [
                    'dateTime' => $endsAt->toIso8601String(),
                    'timeZone' => config('app.timezone'),
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Google Calendar event creation failed: '.$response->body());
        }

        $eventId = $response->json('id');

        if (! is_string($eventId) || $eventId === '') {
            throw new \RuntimeException('Google Calendar event id was not returned.');
        }

        return $eventId;
    }

    /**
     * Google Calendar のイベントを削除する
     */
    public function deleteEvent(GoogleCalendarConnection $connection, string $eventId): void
    {
        $connection = $this->refreshAccessTokenIfNeeded($connection);

        $encodedEventId = rawurlencode($eventId);

        $response = Http::withToken($connection->access_token)
            ->delete("https://www.googleapis.com/calendar/v3/calendars/primary/events/{$encodedEventId}");

        if ($response->failed() && $response->status() !== 404) {
            throw new \RuntimeException('Google Calendar event deletion failed: '.$response->body());
        }
    }
}