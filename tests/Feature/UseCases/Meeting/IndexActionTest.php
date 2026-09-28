<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_viewers_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $own = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $other = Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $result = app(IndexAction::class)($student, 'upcoming');

        $meetings = $result['meetings'];

        $this->assertTrue($meetings->contains('id', $own->id));
        $this->assertFalse($meetings->contains('id', $other->id));
    }

    public function test_filters_upcoming_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $upcomingMeeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $pastMeeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(4)->startOfHour(),
        ]);

        $result = app(IndexAction::class)($student, 'upcoming');

        $meetings = $result['meetings'];

        $this->assertTrue($meetings->contains('id', $upcomingMeeting->id));
        $this->assertFalse($meetings->contains('id', $pastMeeting->id));
    }

    public function test_filters_past_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $upcomingMeeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $pastMeeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(4)->startOfHour(),
        ]);

        $result = app(IndexAction::class)($student, 'past');

        $meetings = $result['meetings'];

        $this->assertTrue($meetings->contains('id', $pastMeeting->id));
        $this->assertFalse($meetings->contains('id', $upcomingMeeting->id));
    }

    public function test_filters_all_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $upcomingMeeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $pastMeeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(4)->startOfHour(),
        ]);

        $result = app(IndexAction::class)($student, 'all');

        $meetings = $result['meetings'];

        $this->assertTrue($meetings->contains('id', $upcomingMeeting->id));
        $this->assertTrue($meetings->contains('id', $pastMeeting->id));
    }

    public function test_returns_remaining_meeting_count(): void
    {
        $student = User::factory()->student()->create(['max_meetings' => 4]);

        $result = app(IndexAction::class)($student, 'upcoming');

        $this->assertSame(4, $result['remaining']);
    }
}
