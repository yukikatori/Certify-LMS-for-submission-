<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAsCoachAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexAsCoachActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_viewers_meetings(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $own = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $other = Meeting::factory()->reserved()->forCoach($otherCoach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $meetings = app(IndexAsCoachAction::class)(
            viewer: $coach,
            filter: 'upcoming',
            studentId: null,
            enrollmentId: null,
        );

        $this->assertTrue($meetings->contains('id', $own->id));
        $this->assertFalse($meetings->contains('id', $other->id));
    }

    public function test_filters_meetings_by_student(): void
    {
        $coach = User::factory()->coach()->create();
        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();

        $student1Meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student1)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $student2Meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student2)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $meetings = app(IndexAsCoachAction::class)(
            viewer: $coach,
            filter: 'upcoming',
            studentId: $student1->id,
            enrollmentId: null,
        );

        $this->assertTrue($meetings->contains('id', $student1Meeting->id));
        $this->assertFalse($meetings->contains('id', $student2Meeting->id));
    }

    public function test_filters_meetings_by_enrollment(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $certification1 = Certification::factory()->published()->create();
        $certification2 = Certification::factory()->published()->create();

        $enrollment1 = Enrollment::factory()->for($student, 'user')->for($certification1)->learning()->create();
        $enrollment2 = Enrollment::factory()->for($student, 'user')->for($certification2)->learning()->create();

        $enrollment1Meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->forEnrollment($enrollment1)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $enrollment2Meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->forEnrollment($enrollment2)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $meetings = app(IndexAsCoachAction::class)(
            viewer: $coach,
            filter: 'upcoming',
            studentId: null,
            enrollmentId: $enrollment1->id,
        );

        $this->assertTrue($meetings->contains('id', $enrollment1Meeting->id));
        $this->assertFalse($meetings->contains('id', $enrollment2Meeting->id));
    }

    public function test_filters_upcoming_meetings_in_ascending_order(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $meeting1 = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);
        $meeting2 = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $meetings = app(IndexAsCoachAction::class)(
            viewer: $coach,
            filter: 'upcoming',
            studentId: null,
            enrollmentId: null,
        );

        $this->assertSame(
            [
                $meeting2->id,
                $meeting1->id,
            ],
            $meetings->pluck('id')->all(),
        );
    }

    public function test_filters_past_meetings_in_descending_order(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $meeting1 = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(4)->startOfHour(),
        ]);
        $meeting2 = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(3)->startOfHour(),
        ]);

        $meetings = app(IndexAsCoachAction::class)(
            viewer: $coach,
            filter: 'past',
            studentId: null,
            enrollmentId: null,
        );

        $this->assertSame(
            [
                $meeting2->id,
                $meeting1->id,
            ],
            $meetings->pluck('id')->all(),
        );
    }

    public function test_filters_all_meetings_in_descending_order(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $meeting1 = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(4)->startOfHour(),
        ]);
        $meeting2 = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $meetings = app(IndexAsCoachAction::class)(
            viewer: $coach,
            filter: 'all',
            studentId: null,
            enrollmentId: null,
        );

        $this->assertSame(
            [
                $meeting2->id,
                $meeting1->id,
            ],
            $meetings->pluck('id')->all(),
        );
    }
}
