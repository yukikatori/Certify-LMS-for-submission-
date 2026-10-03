<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use App\Policies\EnrollmentGoalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EnrollmentGoalPolicy の判定を検証する Unit テスト。
 * create / update / delete / markAchieved / unmarkAchieved は受講生本人のみ許可する。
 */
class EnrollmentGoalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_allowed_for_owning_student(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $policy = new EnrollmentGoalPolicy;

        $this->assertTrue($policy->create($student, $enrollment));
    }

    public function test_create_denied_for_other_student(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($other)->learning()->create();
        $policy = new EnrollmentGoalPolicy;

        $this->assertFalse($policy->create($student, $enrollment), '他人の enrollment には目標作成不可');
    }

    public function test_create_denied_for_coach_and_admin(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $policy = new EnrollmentGoalPolicy;

        $this->assertFalse($policy->create($coach, $enrollment));
        $this->assertFalse($policy->create($admin, $enrollment));
    }

    public function test_create_denied_for_non_learning_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $passedEnrollment = Enrollment::factory()->for($student)->passed()->create();
        $failedEnrollment = Enrollment::factory()->for($student)->failed()->create();
        $policy = new EnrollmentGoalPolicy;

        $this->assertFalse($policy->create($student, $passedEnrollment));
        $this->assertFalse($policy->create($student, $failedEnrollment));
    }

    public function test_owner_student_can_update_delete_and_toggle_achievement(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment);
        $policy = new EnrollmentGoalPolicy;

        $this->assertTrue($policy->update($student, $goal));
        $this->assertTrue($policy->delete($student, $goal));
        $this->assertTrue($policy->markAchieved($student, $goal));
        $this->assertTrue($policy->unmarkAchieved($student, $goal));
    }

    public function test_other_student_cannot_update_delete_or_toggle_achievement(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($other)->learning()->create();
        $goal = $this->createGoal($enrollment);
        $policy = new EnrollmentGoalPolicy;

        $this->assertFalse($policy->update($student, $goal));
        $this->assertFalse($policy->delete($student, $goal));
        $this->assertFalse($policy->markAchieved($student, $goal));
        $this->assertFalse($policy->unmarkAchieved($student, $goal));
    }

    public function test_coach_and_admin_cannot_update_delete_or_toggle_achievement(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $goal = $this->createGoal();
        $policy = new EnrollmentGoalPolicy;

        $this->assertFalse($policy->update($coach, $goal));
        $this->assertFalse($policy->delete($coach, $goal));
        $this->assertFalse($policy->markAchieved($coach, $goal));
        $this->assertFalse($policy->unmarkAchieved($coach, $goal));

        $this->assertFalse($policy->update($admin, $goal));
        $this->assertFalse($policy->delete($admin, $goal));
        $this->assertFalse($policy->markAchieved($admin, $goal));
        $this->assertFalse($policy->unmarkAchieved($admin, $goal));
    }

    public function test_owner_student_cannot_update_delete_or_toggle_non_learning_enrollment_goal(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->passed()->create();
        $goal = $this->createGoal($enrollment);
        $policy = new EnrollmentGoalPolicy;

        $this->assertFalse($policy->update($student, $goal));
        $this->assertFalse($policy->delete($student, $goal));
        $this->assertFalse($policy->markAchieved($student, $goal));
        $this->assertFalse($policy->unmarkAchieved($student, $goal));
    }

    private function createGoal(?Enrollment $enrollment = null): EnrollmentGoal
    {
        $enrollment ??= Enrollment::factory()->learning()->create();

        return EnrollmentGoal::create([
            'user_id' => $enrollment->user_id,
            'enrollment_id' => $enrollment->id,
            'title' => '基礎講座を一通り終える',
            'target_date' => now()->addDays(10)->toDateString(),
            'description' => '第 1 章から最終章までを視聴する。',
            'achieved_at' => null,
        ]);
    }
}
