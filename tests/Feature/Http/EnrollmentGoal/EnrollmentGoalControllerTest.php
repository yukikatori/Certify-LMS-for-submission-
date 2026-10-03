<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 個人学習目標 CRUD / 達成切替の HTTP 統合テスト。
 */
class EnrollmentGoalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_goal_for_owner_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $this->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '過去問 5 年分を解き終える',
                'target_date' => now()->addWeek()->toDateString(),
                'description' => '間違えた問題をノートにまとめる。',
            ])
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'title' => '過去問 5 年分を解き終える',
            'description' => '間違えた問題をノートにまとめる。',
            'achieved_at' => null,
        ]);
    }

    public function test_edit_returns_view_for_owner_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment);

        $this->actingAs($student)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertOk()
            ->assertViewIs('enrollment-goal.edit')
            ->assertViewHas('goal', fn ($viewGoal) => $viewGoal->is($goal));
    }

    public function test_update_changes_goal_for_owner_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment);

        $this->actingAs($student)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '章末問題を 2 章分解く',
                'target_date' => now()->addWeeks(2)->toDateString(),
                'description' => '正答率 80% を目安に復習する。',
            ])
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '章末問題を 2 章分解く',
            'description' => '正答率 80% を目安に復習する。',
        ]);
    }

    public function test_destroy_deletes_goal_for_owner_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment);

        $this->actingAs($student)
            ->delete(route('enrollment-goals.destroy', $goal))
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }

    public function test_mark_achieve_sets_achieved_at_for_owner_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment);

        $this->actingAs($student)
            ->post(route('enrollment-goals.markAchieved', $goal))
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertNotNull($goal->fresh()->achieved_at);
    }

    public function test_unmark_achieve_clears_achieved_at_for_owner_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment, ['achieved_at' => now()]);

        $this->actingAs($student)
            ->delete(route('enrollment-goals.unmarkAchieved', $goal))
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertNull($goal->fresh()->achieved_at);
    }

    public function test_store_rejects_other_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($other)->learning()->create();

        $this->actingAs($student)
            ->postJson(route('enrollments.goals.store', $enrollment), [
                'title' => '過去問 5 年分を解き終える',
                'target_date' => now()->addWeek()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_goal_operations_reject_other_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($other)->learning()->create();
        $goal = $this->createGoal($enrollment);

        $this->actingAs($student)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertForbidden();

        $this->actingAs($student)
            ->patchJson(route('enrollment-goals.update', $goal), [
                'title' => '過去問 5 年分を解き終える',
                'target_date' => now()->addWeek()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->deleteJson(route('enrollment-goals.destroy', $goal))
            ->assertForbidden();

        $this->actingAs($student)
            ->postJson(route('enrollment-goals.markAchieved', $goal))
            ->assertForbidden();

        $this->actingAs($student)
            ->deleteJson(route('enrollment-goals.unmarkAchieved', $goal))
            ->assertForbidden();
    }

    public function test_coach_and_admin_cannot_use_goal_operation_routes(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $goal = $this->createGoal();

        $this->actingAs($coach)
            ->postJson(route('enrollment-goals.markAchieved', $goal))
            ->assertForbidden();

        $this->actingAs($admin)
            ->deleteJson(route('enrollment-goals.destroy', $goal))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();

        $this->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問 5 年分を解き終える',
            'target_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect(route('login'));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createGoal(?Enrollment $enrollment = null, array $attributes = []): EnrollmentGoal
    {
        $enrollment ??= Enrollment::factory()->learning()->create();

        return EnrollmentGoal::create(array_merge([
            'user_id' => $enrollment->user_id,
            'enrollment_id' => $enrollment->id,
            'title' => '基礎講座を一通り終える',
            'target_date' => now()->addDays(10)->toDateString(),
            'description' => '第 1 章から最終章までを視聴する。',
            'achieved_at' => null,
        ], $attributes));
    }
}
