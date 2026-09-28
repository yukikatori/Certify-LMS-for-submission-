<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Meeting\CreateFallbackAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateFallbackActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_learning_and_passed_enrollments(): void
    {
        $student = User::factory()->student()->create();

        $learningEnrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for(Certification::factory()->published())
            ->learning()
            ->create();

        $passedEnrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for(Certification::factory()->published())
            ->passed()
            ->create();

        $failedEnrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for(Certification::factory()->published())
            ->failed()
            ->create();

        $enrollments = app(CreateFallbackAction::class)($student);

        $this->assertTrue($enrollments->contains('id', $learningEnrollment->id));
        $this->assertTrue($enrollments->contains('id', $passedEnrollment->id));
        $this->assertFalse($enrollments->contains('id', $failedEnrollment->id));
    }

    public function test_loads_certification_relation(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $enrollments = app(CreateFallbackAction::class)($student);

        $result = $enrollments->firstWhere('id', $enrollment->id);

        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('certification'));
    }
}
