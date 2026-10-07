<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('external-api')]
class GoogleCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_calendar.client_id' => 'google-client-id',
            'services.google_calendar.client_secret' => 'google-client-secret',
            'services.google_calendar.redirect_uri' => 'https://lms.example.test/settings/google-calendar/callback',
        ]);
    }

    public function test_connect_redirects_to_google_oauth_and_stores_state(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->get(route('settings.google-calendar.redirect', [
                'redirect_path' => '/settings/availability',
            ]));

        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('google-client-id', $query['client_id']);
        $this->assertSame('https://lms.example.test/settings/google-calendar/callback', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertIsString($query['state']);
        $this->assertSame($query['state'], session('google_calendar_oauth_state'));
        $this->assertSame('/settings/availability', session('google_calendar_redirect_path'));
        $this->assertStringContainsString('https://www.googleapis.com/auth/calendar', $query['scope']);
    }

    public function test_callback_exchanges_code_and_stores_connection_without_real_http(): void
    {
        $coach = User::factory()->coach()->create();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
            ]),
            'https://www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'id' => 'google-user-123',
                'email' => 'coach.google@example.com',
            ]),
        ]);

        $response = $this->actingAs($coach)
            ->withSession([
                'google_calendar_oauth_state' => 'valid-state',
                'google_calendar_redirect_path' => '/settings/availability',
            ])
            ->get(route('settings.google-calendar.callback', [
                'state' => 'valid-state',
                'code' => 'auth-code',
            ]));

        $response->assertRedirect('/settings/availability');
        $response->assertSessionHas('success', 'Googleカレンダーと連携しました。');

        $this->assertDatabaseHas('google_calendar_connections', [
            'coach_id' => $coach->id,
            'google_account_id' => 'google-user-123',
            'google_email' => 'coach.google@example.com',
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://oauth2.googleapis.com/token'
                && $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'auth-code';
        });

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.googleapis.com/oauth2/v2/userinfo');
    }

    public function test_callback_falls_back_when_token_exchange_fails(): void
    {
        $coach = User::factory()->coach()->create();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $response = $this->actingAs($coach)
            ->withSession([
                'google_calendar_oauth_state' => 'valid-state',
                'google_calendar_redirect_path' => '/settings/availability',
            ])
            ->get(route('settings.google-calendar.callback', [
                'state' => 'valid-state',
                'code' => 'bad-code',
            ]));

        $response->assertRedirect('/settings/availability');
        $response->assertSessionHas('error', 'Googleカレンダー連携に失敗しました。時間をおいて再度お試しください。');
        $this->assertDatabaseMissing('google_calendar_connections', [
            'coach_id' => $coach->id,
        ]);
    }

    public function test_destroy_revokes_token_and_deletes_connection_without_real_http(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarConnection::factory()->forCoach($coach)->create([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/revoke' => Http::response([], 200),
        ]);

        $response = $this->actingAs($coach)
            ->delete(route('settings.google-calendar.destroy'));

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('success', 'Googleカレンダー連携を解除しました。');
        $this->assertDatabaseMissing('google_calendar_connections', [
            'coach_id' => $coach->id,
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://oauth2.googleapis.com/revoke'
                && $request['token'] === 'refresh-token';
        });
    }
}
