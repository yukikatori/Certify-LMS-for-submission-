<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('external-api')]
class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Tokyo',
            'services.google_calendar.client_id' => 'google-client-id',
            'services.google_calendar.client_secret' => 'google-client-secret',
        ]);
    }

    public function test_fetch_busy_periods_returns_google_busy_ranges(): void
    {
        $connection = GoogleCalendarConnection::factory()->create([
            'access_token' => 'valid-access-token',
            'token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [
                    'primary' => [
                        'busy' => [
                            [
                                'start' => '2026-06-01T10:00:00+09:00',
                                'end' => '2026-06-01T11:00:00+09:00',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $busyPeriods = app(GoogleCalendarService::class)->fetchBusyPeriods(
            $connection,
            CarbonImmutable::parse('2026-06-01 09:00:00', 'Asia/Tokyo'),
            CarbonImmutable::parse('2026-06-01 18:00:00', 'Asia/Tokyo'),
        );

        $this->assertCount(1, $busyPeriods);
        $this->assertSame('2026-06-01T10:00:00+09:00', $busyPeriods[0]['start']->toIso8601String());
        $this->assertSame('2026-06-01T11:00:00+09:00', $busyPeriods[0]['end']->toIso8601String());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://www.googleapis.com/calendar/v3/freeBusy'
                && $request->hasHeader('Authorization', 'Bearer valid-access-token')
                && $request['timeMin'] === '2026-06-01T09:00:00+09:00'
                && $request['timeMax'] === '2026-06-01T18:00:00+09:00';
        });
    }

    public function test_expired_token_is_refreshed_before_calendar_request(): void
    {
        $connection = GoogleCalendarConnection::factory()->expired()->create([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed-access-token',
                'expires_in' => 7200,
            ]),
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => ['primary' => ['busy' => []]],
            ]),
        ]);

        app(GoogleCalendarService::class)->fetchBusyPeriods(
            $connection,
            CarbonImmutable::parse('2026-06-01 09:00:00', 'Asia/Tokyo'),
            CarbonImmutable::parse('2026-06-01 18:00:00', 'Asia/Tokyo'),
        );

        $this->assertDatabaseHas('google_calendar_connections', [
            'id' => $connection->id,
            'access_token' => 'refreshed-access-token',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://oauth2.googleapis.com/token'
                && $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === 'refresh-token';
        });

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://www.googleapis.com/calendar/v3/freeBusy'
                && $request->hasHeader('Authorization', 'Bearer refreshed-access-token');
        });
    }

    public function test_refresh_failure_throws_before_retrying_calendar_request(): void
    {
        $connection = GoogleCalendarConnection::factory()->expired()->create([
            'refresh_token' => 'refresh-token',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        try {
            app(GoogleCalendarService::class)->fetchBusyPeriods(
                $connection,
                CarbonImmutable::parse('2026-06-01 09:00:00', 'Asia/Tokyo'),
                CarbonImmutable::parse('2026-06-01 18:00:00', 'Asia/Tokyo'),
            );

            $this->fail('Expected Google token refresh failure.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Google OAuth token refresh failed', $e->getMessage());
        }

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://www.googleapis.com/calendar/v3/freeBusy');
    }

    public function test_create_event_posts_event_payload_and_returns_event_id(): void
    {
        $connection = GoogleCalendarConnection::factory()->create([
            'access_token' => 'valid-access-token',
            'token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response([
                'id' => 'google-event-123',
            ]),
        ]);

        $eventId = app(GoogleCalendarService::class)->createEvent(
            $connection,
            'LMS面談',
            CarbonImmutable::parse('2026-06-01 10:00:00', 'Asia/Tokyo'),
            CarbonImmutable::parse('2026-06-01 11:00:00', 'Asia/Tokyo'),
            '相談内容',
            'https://meet.example.com/coach-room',
        );

        $this->assertSame('google-event-123', $eventId);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://www.googleapis.com/calendar/v3/calendars/primary/events'
                && $request->hasHeader('Authorization', 'Bearer valid-access-token')
                && $request['summary'] === 'LMS面談'
                && $request['description'] === '相談内容'
                && $request['location'] === 'https://meet.example.com/coach-room'
                && $request['start']['dateTime'] === '2026-06-01T10:00:00+09:00'
                && $request['end']['dateTime'] === '2026-06-01T11:00:00+09:00';
        });
    }

    public function test_create_event_throws_when_response_has_no_event_id(): void
    {
        $connection = GoogleCalendarConnection::factory()->create([
            'token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response([]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Calendar event id was not returned.');

        app(GoogleCalendarService::class)->createEvent(
            $connection,
            'LMS面談',
            CarbonImmutable::parse('2026-06-01 10:00:00', 'Asia/Tokyo'),
            CarbonImmutable::parse('2026-06-01 11:00:00', 'Asia/Tokyo'),
        );
    }

    public function test_delete_event_succeeds_and_encodes_event_id(): void
    {
        $connection = GoogleCalendarConnection::factory()->create([
            'access_token' => 'valid-access-token',
            'token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/event%2Fwith%20space' => Http::response([], 204),
        ]);

        app(GoogleCalendarService::class)->deleteEvent($connection, 'event/with space');

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'DELETE'
                && $request->url() === 'https://www.googleapis.com/calendar/v3/calendars/primary/events/event%2Fwith%20space'
                && $request->hasHeader('Authorization', 'Bearer valid-access-token');
        });
    }

    public function test_delete_event_treats_google_404_as_already_deleted(): void
    {
        $connection = GoogleCalendarConnection::factory()->create([
            'token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/deleted-event' => Http::response([], 404),
        ]);

        app(GoogleCalendarService::class)->deleteEvent($connection, 'deleted-event');

        $this->addToAssertionCount(1);
    }
}
