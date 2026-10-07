<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Announcement;

use App\Jobs\SendAnnouncementNotificationsJob;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * StoreAction uses DB::afterCommit(), so this test class must not wrap
     * each test in the outer transaction created by RefreshDatabase.
     *
     * @var array<int, string|null>
     */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();

        foreach ([
            'notifications',
            'announcements',
            'enrollments',
            'certifications',
            'certification_categories',
            'users',
        ] as $table) {
            DB::table($table)->delete();
        }

        Schema::enableForeignKeyConstraints();
    }

    public function test_all_students_target_dispatches_notification_job_and_records_count(): void
    {
        Bus::fake();

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
        Bus::assertDispatched(SendAnnouncementNotificationsJob::class);
        $this->assertSame(0, $firstStudent->notifications()->count());
        $this->assertSame(0, $secondStudent->notifications()->count());
        $this->assertSame(0, $graduatedStudent->notifications()->count());
        $this->assertSame(0, $coach->notifications()->count());
    }

    public function test_certification_target_dispatches_notification_job_and_records_active_learning_count(): void
    {
        Bus::fake();

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
        Bus::assertDispatched(SendAnnouncementNotificationsJob::class);
        $this->assertSame(0, $targetStudent->notifications()->count());
        $this->assertSame(0, $secondTargetStudent->notifications()->count());
        $this->assertSame(0, $passedStudent->notifications()->count());
        $this->assertSame(0, $otherCertificationStudent->notifications()->count());
        $this->assertSame(0, $graduatedStudent->notifications()->count());
    }

    public function test_user_target_dispatches_notification_job_for_active_student(): void
    {
        Bus::fake();

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
        Bus::assertDispatched(SendAnnouncementNotificationsJob::class);
        $this->assertSame(0, $targetStudent->notifications()->count());
        $this->assertSame(0, $otherStudent->notifications()->count());
    }

    public function test_user_target_records_zero_dispatches_when_selected_student_is_not_active(): void
    {
        Bus::fake();

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
        Bus::assertDispatched(SendAnnouncementNotificationsJob::class);
        $this->assertSame(0, $graduatedStudent->notifications()->count());
    }

    public function test_announcement_notification_job_sends_to_active_students_only(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $firstStudent = User::factory()->student()->inProgress()->create();
        $secondStudent = User::factory()->student()->inProgress()->create();
        $graduatedStudent = User::factory()->student()->graduated()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $announcement = Announcement::create([
            'title' => '全体お知らせ',
            'body' => '全体本文です。',
            'target_type' => 'all',
            'dispatched_count' => 2,
            'dispatched_at' => now(),
            'created_by' => $admin->id,
        ]);

        (new SendAnnouncementNotificationsJob((string) $announcement->id))->handle();

        $this->assertAnnouncementNotificationSent($firstStudent, $announcement);
        $this->assertAnnouncementNotificationSent($secondStudent, $announcement);
        Notification::assertNotSentTo($graduatedStudent, BusinessEventNotification::class);
        Notification::assertNotSentTo($coach, BusinessEventNotification::class);
    }

    public function test_announcement_notification_job_uses_notifications_queue_and_retry_backoff(): void
    {
        $job = new SendAnnouncementNotificationsJob('01HN0000000000000000000000');

        $this->assertSame('notifications', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff());
    }

    public function test_announcement_notification_job_sends_to_active_learning_students_for_certification_target(): void
    {
        Notification::fake();

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

        $announcement = Announcement::create([
            'title' => '資格別お知らせ',
            'body' => '資格別本文です。',
            'target_type' => 'certification',
            'target_certification_id' => $targetCertification->id,
            'dispatched_count' => 2,
            'dispatched_at' => now(),
            'created_by' => $admin->id,
        ]);

        (new SendAnnouncementNotificationsJob((string) $announcement->id))->handle();

        $this->assertAnnouncementNotificationSent($targetStudent, $announcement);
        $this->assertAnnouncementNotificationSent($secondTargetStudent, $announcement);
        Notification::assertNotSentTo($passedStudent, BusinessEventNotification::class);
        Notification::assertNotSentTo($otherCertificationStudent, BusinessEventNotification::class);
        Notification::assertNotSentTo($graduatedStudent, BusinessEventNotification::class);
    }

    public function test_announcement_notification_job_sends_to_selected_active_student_only(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $targetStudent = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::create([
            'title' => '個別お知らせ',
            'body' => '個別本文です。',
            'target_type' => 'user',
            'target_user_id' => $targetStudent->id,
            'dispatched_count' => 1,
            'dispatched_at' => now(),
            'created_by' => $admin->id,
        ]);

        (new SendAnnouncementNotificationsJob((string) $announcement->id))->handle();

        $this->assertAnnouncementNotificationSent($targetStudent, $announcement);
        Notification::assertNotSentTo($otherStudent, BusinessEventNotification::class);
    }

    private function assertAnnouncementNotificationSent(User $user, Announcement $announcement): void
    {
        Notification::assertSentTo(
            $user,
            BusinessEventNotification::class,
            function (BusinessEventNotification $notification) use ($user, $announcement): bool {
                $data = $notification->toArray($user);

                return $data['notification_type'] === 'admin_announcement'
                    && $data['title'] === $announcement->title
                    && $data['message'] === '運営からのお知らせがあります。'
                    && $data['body'] === $announcement->body
                    && $data['related_type'] === 'announcement'
                    && $data['related_id'] === (string) $announcement->id;
            },
        );
    }
}
