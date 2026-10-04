<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_announcement_index(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = $this->createAnnouncement($admin, ['title' => '履歴に表示されるお知らせ']);

        $response = $this->actingAs($admin)->get(route('admin.announcements.index'));

        $response->assertOk();
        $response->assertViewIs('announcement.management.index');
        $response->assertViewHas('announcements', fn ($announcements) => $announcements
            ->getCollection()
            ->contains('id', $announcement->id));
        $response->assertSee('履歴に表示されるお知らせ');
    }

    public function test_non_admin_cannot_view_announcement_index(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('admin.announcements.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_create_form_with_active_student_options(): void
    {
        $admin = User::factory()->admin()->create();
        $activeStudent = User::factory()->student()->inProgress()->create(['name' => '表示される受講生']);
        User::factory()->student()->graduated()->create(['name' => '表示されない卒業生']);
        $certification = Certification::factory()->published()->create(['name' => '表示される資格']);

        $response = $this->actingAs($admin)->get(route('admin.announcements.create'));

        $response->assertOk();
        $response->assertViewIs('announcement.management.create');
        $response->assertViewHas('students', fn ($students) => $students
            ->contains('id', $activeStudent->id)
            && ! $students->contains('name', '表示されない卒業生'));
        $response->assertViewHas('certifications', fn ($certifications) => $certifications
            ->contains('id', $certification->id));
        $response->assertSee('表示される受講生');
        $response->assertDontSee('表示されない卒業生');
        $response->assertSee('表示される資格');
    }

    public function test_admin_can_store_announcement_and_redirect_to_show(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '配信するお知らせ',
            'body' => 'お知らせ本文です。',
            'target_type' => 'user',
            'target_user_id' => $student->id,
        ]);

        $announcement = Announcement::query()->where('title', '配信するお知らせ')->firstOrFail();

        $response->assertRedirect(route('admin.announcements.show', $announcement));
        $response->assertSessionHas('success', 'お知らせを作成しました。');
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $student->getMorphClass(),
            'notifiable_id' => $student->id,
        ]);
    }

    public function test_admin_can_view_announcement_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = $this->createAnnouncement($admin, [
            'title' => '詳細のお知らせ',
            'body' => '詳細本文です。',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.announcements.show', $announcement));

        $response->assertOk();
        $response->assertViewIs('announcement.management.show');
        $response->assertViewHas('announcement', fn ($viewAnnouncement) => $viewAnnouncement->id === $announcement->id);
        $response->assertSee('詳細のお知らせ');
        $response->assertSee('詳細本文です。');
    }

    public function test_mutation_routes_for_edit_update_and_destroy_are_not_registered(): void
    {
        $this->assertFalse(Route::has('admin.announcements.edit'));
        $this->assertFalse(Route::has('admin.announcements.update'));
        $this->assertFalse(Route::has('admin.announcements.destroy'));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createAnnouncement(User $admin, array $overrides = []): Announcement
    {
        $announcement = new Announcement;
        $announcement->forceFill(array_merge([
            'title' => 'お知らせ',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
            'dispatched_count' => 0,
            'dispatched_at' => now(),
            'created_by' => $admin->id,
        ], $overrides));
        $announcement->save();

        return $announcement;
    }
}
