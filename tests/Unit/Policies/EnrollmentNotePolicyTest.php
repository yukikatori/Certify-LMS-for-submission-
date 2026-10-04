<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use App\Policies\EnrollmentNotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentNotePolicyTest extends TestCase
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

    public function test_view_any_and_create_allow_admin_and_assigned_coach_only(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->create();
        $policy = new EnrollmentNotePolicy;

        $this->attachCoach($certification, $coach, $admin);

        $this->assertTrue($policy->viewAny($admin, $enrollment));
        $this->assertTrue($policy->create($admin, $enrollment));
        $this->assertTrue($policy->viewAny($coach, $enrollment));
        $this->assertTrue($policy->create($coach, $enrollment));
        $this->assertFalse($policy->viewAny($otherCoach, $enrollment));
        $this->assertFalse($policy->create($otherCoach, $enrollment));
        $this->assertFalse($policy->viewAny($student, $enrollment));
        $this->assertFalse($policy->create($student, $enrollment));
    }

    public function test_update_and_delete_allow_author_coach_and_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();
        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($author)
            ->create();
        $policy = new EnrollmentNotePolicy;

        $this->attachCoach($certification, $author, $admin);
        $this->attachCoach($certification, $otherCoach, $admin);

        $this->assertTrue($policy->update($admin, $note));
        $this->assertTrue($policy->delete($admin, $note));
        $this->assertTrue($policy->update($author, $note));
        $this->assertTrue($policy->delete($author, $note));
        $this->assertFalse($policy->update($otherCoach, $note));
        $this->assertFalse($policy->delete($otherCoach, $note));
    }

    public function test_author_coach_cannot_update_or_delete_note_after_losing_assignment(): void
    {
        $author = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();
        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($author)
            ->create();
        $policy = new EnrollmentNotePolicy;

        $this->assertFalse($policy->update($author, $note));
        $this->assertFalse($policy->delete($author, $note));
    }
}
