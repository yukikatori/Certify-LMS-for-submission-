<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPopoverVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_see_notification_popover_trigger(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertSee('data-notification-popover-root', false);
        $response->assertSee('data-notification-popover-trigger', false);
    }

    public function test_coach_can_see_notification_popover_trigger(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $response = $this->actingAs($coach)->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertSee('data-notification-popover-root', false);
        $response->assertSee('data-notification-popover-trigger', false);
    }

    public function test_admin_cannot_see_notification_popover_trigger(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $response = $this->actingAs($admin)->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertDontSee('data-notification-popover-root', false);
        $response->assertDontSee('data-notification-popover-trigger', false);
    }
}
