<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateRequestTest extends TestCase
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
        $author = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();
        $this->attachCoach($certification, $author, $admin);
        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($author)
            ->create();

        $response = $this->actingAs($author)->patch(route('enrollment-notes.update', $note), [
            'body' => '更新後のメモ',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('enrollments.show', $enrollment));
    }

    #[DataProvider('invalidBodyPayloads')]
    public function test_validation_fails_for_body(array $payload): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();
        $this->attachCoach($certification, $author, $admin);
        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($author)
            ->create(['body' => '更新前']);

        $response = $this->actingAs($author)->patchJson(route('enrollment-notes.update', $note), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('body');
        $this->assertSame('更新前', $note->fresh()->body);
    }

    public function test_authorize_returns_false_for_other_coach(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();
        $this->attachCoach($certification, $author, $admin);
        $this->attachCoach($certification, $otherCoach, $admin);
        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($author)
            ->create(['body' => '更新前']);

        $response = $this->actingAs($otherCoach)->patchJson(route('enrollment-notes.update', $note), [
            'body' => '他コーチは更新できない',
        ]);

        $response->assertForbidden();
        $this->assertSame('更新前', $note->fresh()->body);
    }

    public function test_authorize_returns_true_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->coach()->create();
        $note = EnrollmentNote::factory()
            ->forAuthor($author)
            ->create();

        $response = $this->actingAs($admin)->patch(route('enrollment-notes.update', $note), [
            'body' => '管理者による更新',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('管理者による更新', $note->fresh()->body);
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
