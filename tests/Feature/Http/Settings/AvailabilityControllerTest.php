<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconnected_coach_sees_google_calendar_connect_action(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->get(route('settings.availability.index'));

        $response->assertOk();
        $response->assertSee('Googleカレンダー連携');
        $response->assertSee('未連携');
        $response->assertSee('Googleカレンダーと連携する');
        $response->assertSee(route('settings.google-calendar.redirect', [
            'redirect_path' => '/settings/availability',
        ]));
        $response->assertDontSee('連携を解除する');
    }

    public function test_connected_coach_sees_google_calendar_disconnect_action_and_account(): void
    {
        $coach = User::factory()->coach()->create();

        GoogleCalendarConnection::query()->create([
            'coach_id' => $coach->id,
            'google_account_id' => 'google-user-123',
            'google_email' => 'coach.google@example.com',
            'access_token' => 'demo-access-token',
            'refresh_token' => 'demo-refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $response = $this->actingAs($coach)->get(route('settings.availability.index'));

        $response->assertOk();
        $response->assertSee('Googleカレンダー連携');
        $response->assertSee('連携中');
        $response->assertSee('coach.google@example.com');
        $response->assertSee('連携を解除する');
        $response->assertSee(route('settings.google-calendar.destroy'));
        $response->assertDontSee('Googleカレンダーと連携する');
    }
}
