<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentNoteControllerTest extends TestCase
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

    public function test_store_creates_note_for_assigned_coach(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();
        $this->attachCoach($certification, $coach, $admin);

        $response = $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '次回面談で確認する',
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
            'body' => '次回面談で確認する',
        ]);
    }

    public function test_store_allows_admin_for_any_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        $response = $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '管理者メモ',
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $admin->id,
            'body' => '管理者メモ',
        ]);
    }

    public function test_store_rejects_unassigned_coach_and_student(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create();

        $this->actingAs($coach)
            ->postJson(route('enrollments.notes.store', $enrollment), ['body' => '担当外'])
            ->assertForbidden();

        $this->actingAs($student)
            ->postJson(route('enrollments.notes.store', $enrollment), ['body' => '本人'])
            ->assertForbidden();

        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    public function test_edit_allows_author_and_admin_but_forbids_other_coach(): void
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
            ->create(['body' => '編集対象メモ']);

        $this->actingAs($author)->get(route('enrollment-notes.edit', $note))->assertOk();
        $this->actingAs($admin)->get(route('enrollment-notes.edit', $note))->assertOk();
        $this->actingAs($otherCoach)->get(route('enrollment-notes.edit', $note))->assertForbidden();
    }

    public function test_update_allows_author_and_admin_only(): void
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

        $this->actingAs($otherCoach)
            ->patchJson(route('enrollment-notes.update', $note), ['body' => '他コーチ更新'])
            ->assertForbidden();

        $this->actingAs($author)
            ->patch(route('enrollment-notes.update', $note), ['body' => '本人更新'])
            ->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertSame('本人更新', $note->fresh()->body);

        $this->actingAs($admin)
            ->patch(route('enrollment-notes.update', $note), ['body' => '管理者更新'])
            ->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertSame('管理者更新', $note->fresh()->body);
    }

    public function test_destroy_allows_author_and_admin_only(): void
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
            ->create();

        $this->actingAs($otherCoach)
            ->deleteJson(route('enrollment-notes.destroy', $note))
            ->assertForbidden();
        $this->assertDatabaseHas('enrollment_notes', ['id' => $note->id]);

        $this->actingAs($author)
            ->delete(route('enrollment-notes.destroy', $note))
            ->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);

        $adminNote = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($author)
            ->create();
        $this->actingAs($admin)
            ->delete(route('enrollment-notes.destroy', $adminNote))
            ->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseMissing('enrollment_notes', ['id' => $adminNote->id]);
    }

    public function test_enrollment_show_displays_notes_to_assigned_coach_but_hides_section_from_student(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->create();
        $this->attachCoach($certification, $coach, $admin);
        $this->attachCoach($certification, $otherCoach, $admin);
        $ownNote = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($coach)
            ->create(['body' => '自分の観察メモ']);
        $otherNote = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($otherCoach)
            ->create(['body' => '他コーチの申し送り']);

        $coachResponse = $this->actingAs($coach)->get(route('enrollments.show', $enrollment));
        $coachResponse->assertOk();
        $coachResponse->assertSee('コーチメモ');
        $coachResponse->assertSee('自分の観察メモ');
        $coachResponse->assertSee('他コーチの申し送り');
        $coachResponse->assertSee(route('enrollment-notes.edit', $ownNote), false);
        $coachResponse->assertDontSee(route('enrollment-notes.edit', $otherNote), false);

        $studentResponse = $this->actingAs($student)->get(route('enrollments.show', $enrollment));
        $studentResponse->assertOk();
        $studentResponse->assertDontSee('コーチメモ');
        $studentResponse->assertDontSee('自分の観察メモ');
        $studentResponse->assertDontSee('他コーチの申し送り');
    }
}
