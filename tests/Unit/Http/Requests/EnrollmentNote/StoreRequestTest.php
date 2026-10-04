<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_validation_passes_with_valid_body(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();
        $this->attachCoach($certification, $coach, $admin);

        $response = $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '次回面談で確認する',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('enrollments.show', $enrollment));
    }

    #[DataProvider('invalidBodyPayloads')]
    public function test_validation_fails_for_body(array $payload): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();
        $this->attachCoach($certification, $coach, $admin);

        $response = $this->actingAs($coach)->postJson(route('enrollments.notes.store', $enrollment), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('body');
    }

    public function test_authorize_returns_false_for_unassigned_coach(): void
    {
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $response = $this->actingAs($coach)->postJson(route('enrollments.notes.store', $enrollment), [
            'body' => '担当外資格へのメモ',
        ]);

        $response->assertForbidden();
    }

    public function test_authorize_returns_false_for_student(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create();

        $response = $this->actingAs($student)->postJson(route('enrollments.notes.store', $enrollment), [
            'body' => '本人からは作成できない',
        ]);

        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidBodyPayloads(): array
    {
        return [
            'missing' => [[]],
            'empty' => [['body' => '']],
            'too_long' => [['body' => str_repeat('a', 2001)]],
        ];
    }
}
