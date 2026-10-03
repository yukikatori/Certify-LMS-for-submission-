<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 個人学習目標 StoreRequest のバリデーション検証。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問 5 年分を解き終える',
            'target_date' => now()->addWeek()->toDateString(),
            'description' => '間違えた問題をノートにまとめる。',
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'title' => '過去問 5 年分を解き終える',
            'description' => '間違えた問題をノートにまとめる。',
            'achieved_at' => null,
        ]);
    }

    public function test_validation_passes_with_nullable_description(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '基礎講座を一通り終える',
            'target_date' => now()->addWeek()->toDateString(),
            'description' => null,
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'title' => '基礎講座を一通り終える',
            'description' => null,
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $payload = array_merge([
            'title' => '過去問 5 年分を解き終える',
            'target_date' => now()->addWeek()->toDateString(),
            'description' => '間違えた問題をノートにまとめる。',
        ], $overrides);

        $response = $this->actingAs($student)->postJson(route('enrollments.goals.store', $enrollment), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_other_student_cannot_create_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($other)->learning()->create();

        $response = $this->actingAs($student)->postJson(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問 5 年分を解き終える',
            'target_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_coach_cannot_create_goal(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($coach)->postJson(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問 5 年分を解き終える',
            'target_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_admin_cannot_create_goal(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($admin)->postJson(route('enrollments.goals.store', $enrollment), [
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
}
