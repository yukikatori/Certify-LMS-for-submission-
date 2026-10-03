<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 個人学習目標 UpdateRequest のバリデーション検証。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment);

        $response = $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), [
            'title' => '過去問 5 年分を解き終える',
            'target_date' => now()->addWeek()->toDateString(),
            'description' => '間違えた問題をノートにまとめる。',
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '過去問 5 年分を解き終える',
            'description' => '間違えた問題をノートにまとめる。',
        ]);
    }

    public function test_validation_passes_with_nullable_description(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment);

        $response = $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), [
            'title' => '基礎講座を一通り終える',
            'target_date' => now()->addWeek()->toDateString(),
            'description' => null,
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '基礎講座を一通り終える',
            'description' => null,
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment);

        $payload = array_merge([
            'title' => '過去問 5 年分を解き終える',
            'target_date' => now()->addWeek()->toDateString(),
            'description' => '間違えた問題をノートにまとめる。',
        ], $overrides);

        $response = $this->actingAs($student)->patchJson(route('enrollment-goals.update', $goal), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_other_student_cannot_update_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($other)->learning()->create();
        $goal = $this->createGoal($enrollment);

        $response = $this->actingAs($student)->patchJson(route('enrollment-goals.update', $goal), [
            'title' => '過去問 5 年分を解き終える',
            'target_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_coach_cannot_update_goal(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $goal = $this->createGoal();

        $response = $this->actingAs($coach)->patchJson(route('enrollment-goals.update', $goal), [
            'title' => '過去問 5 年分を解き終える',
            'target_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_admin_cannot_update_goal(): void
    {
        $admin = User::factory()->admin()->create();
        $goal = $this->createGoal();

        $response = $this->actingAs($admin)->patchJson(route('enrollment-goals.update', $goal), [
            'title' => '過去問 5 年分を解き終える',
            'target_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'title 未指定で 422' => [['title' => ''], 'title'],
            'title 101 文字で 422' => [['title' => str_repeat('a', 101)], 'title'],
            'target_date 未指定で 422' => [['target_date' => ''], 'target_date'],
            'target_date 不正日付で 422' => [['target_date' => 'not-date'], 'target_date'],
            'target_date 過去日で 422' => [['target_date' => now()->subDay()->toDateString()], 'target_date'],
            'description 1001 文字で 422' => [['description' => str_repeat('a', 1001)], 'description'],
        ];
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
