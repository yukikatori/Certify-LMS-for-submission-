<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Meeting\FetchAvailabilityAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FetchAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_json_ready_slots_for_date(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('10:00:00', '12:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $result = app(FetchAvailabilityAction::class)(
            $enrollment,
            '2026-09-28' // Monday
        );

        $this->assertSame('2026-09-28', $result['date']);

        $this->assertSame(
            [
                [
                    'slot_start' => Carbon::parse('2026-09-28 10:00:00')->toIso8601String(),
                    'slot_end' => Carbon::parse('2026-09-28 11:00:00')->toIso8601String(),
                    'available_coach_count' => 1,
                ],
                [
                    'slot_start' => Carbon::parse('2026-09-28 11:00:00')->toIso8601String(),
                    'slot_end' => Carbon::parse('2026-09-28 12:00:00')->toIso8601String(),
                    'available_coach_count' => 1,
                ],
            ],
            $result['slots'],
        );
    }
}
