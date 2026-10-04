<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Announcement;

use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_students_target_creates_notifications_for_active_students_only(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $firstStudent = User::factory()->student()->inProgress()->create();
        $secondStudent = User::factory()->student()->inProgress()->create();
        $graduatedStudent = User::factory()->student()->graduated()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => '全体お知らせ',
            'body' => '全体本文です。',
            'target_type' => 'all',
        ]);

        $this->assertSame(2, $announcement->dispatched_count);
        $this->assertAnnouncementNotification($firstStudent, $announcement);
        $this->assertAnnouncementNotification($secondStudent, $announcement);
        $this->assertSame(0, $graduatedStudent->notifications()->count());
        $this->assertSame(0, $coach->notifications()->count());
    }

    public function test_certification_target_creates_notifications_for_active_learning_students_only(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $targetCertification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $targetStudent = User::factory()->student()->inProgress()->create();
        $secondTargetStudent = User::factory()->student()->inProgress()->create();
        $passedStudent = User::factory()->student()->inProgress()->create();
        $otherCertificationStudent = User::factory()->student()->inProgress()->create();
        $graduatedStudent = User::factory()->student()->graduated()->create();

        Enrollment::factory()->for($targetStudent, 'user')->for($targetCertification)->learning()->create();
        Enrollment::factory()->for($secondTargetStudent, 'user')->for($targetCertification)->learning()->create();
        Enrollment::factory()->for($passedStudent, 'user')->for($targetCertification)->passed()->create();
        Enrollment::factory()->for($otherCertificationStudent, 'user')->for($otherCertification)->learning()->create();
        Enrollment::factory()->for($graduatedStudent, 'user')->for($targetCertification)->learning()->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => '資格別お知らせ',
            'body' => '資格別本文です。',
            'target_type' => 'certification',
            'target_certification_id' => $targetCertification->id,
        ]);

        $this->assertSame(2, $announcement->dispatched_count);
        $this->assertSame($targetCertification->id, $announcement->target_certification_id);
        $this->assertAnnouncementNotification($targetStudent, $announcement);
        $this->assertAnnouncementNotification($secondTargetStudent, $announcement);
        $this->assertSame(0, $passedStudent->notifications()->count());
        $this->assertSame(0, $otherCertificationStudent->notifications()->count());
        $this->assertSame(0, $graduatedStudent->notifications()->count());
    }

    public function test_user_target_creates_notification_for_active_student(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $targetStudent = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => '個別お知らせ',
            'body' => '個別本文です。',
            'target_type' => 'user',
            'target_user_id' => $targetStudent->id,
        ]);

        $this->assertSame(1, $announcement->dispatched_count);
        $this->assertSame($targetStudent->id, $announcement->target_user_id);
        $this->assertAnnouncementNotification($targetStudent, $announcement);
        $this->assertSame(0, $otherStudent->notifications()->count());
    }

    public function test_user_target_records_zero_dispatches_when_selected_student_is_not_active(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $graduatedStudent = User::factory()->student()->graduated()->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => '対象外個別お知らせ',
            'body' => '対象外本文です。',
            'target_type' => 'user',
            'target_user_id' => $graduatedStudent->id,
        ]);

        $this->assertSame(0, $announcement->dispatched_count);
        $this->assertSame($graduatedStudent->id, $announcement->target_user_id);
        $this->assertSame(0, $graduatedStudent->notifications()->count());
    }

    private function assertAnnouncementNotification(User $user, Announcement $announcement): void
    {
        $notification = $user->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('admin_announcement', $notification->data['notification_type']);
        $this->assertSame($announcement->title, $notification->data['title']);
        $this->assertSame('運営からのお知らせがあります。', $notification->data['message']);
        $this->assertSame($announcement->body, $notification->data['body']);
        $this->assertSame('announcement', $notification->data['related_type']);
        $this->assertSame((string) $announcement->id, $notification->data['related_id']);
    }
}
