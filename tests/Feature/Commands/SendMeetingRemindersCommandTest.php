<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\MeetingStatus;
use App\Jobs\SendMeetingReminderNotificationJob;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_window_dispatches_reminder_jobs_to_reserved_meeting_participants(): void
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = $this->createMeeting($student, $coach, now()->addDay()->setTime(14, 0));

        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->expectsOutput('面談リマインダー対象 1 件を処理しました。')
            ->assertExitCode(0);

        Bus::assertDispatchedTimes(SendMeetingReminderNotificationJob::class, 2);
        $this->assertDatabaseHas('meeting_reminder_deliveries', [
            'meeting_id' => $meeting->id,
            'recipient_user_id' => $student->id,
            'window' => 'eve',
        ]);
        $this->assertDatabaseHas('meeting_reminder_deliveries', [
            'meeting_id' => $meeting->id,
            'recipient_user_id' => $coach->id,
            'window' => 'eve',
        ]);
    }

    public function test_one_hour_before_window_dispatches_reminder_jobs_only_for_next_minute(): void
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-10-05 10:15:20'));

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = $this->createMeeting($student, $coach, now()->addHour()->startOfMinute());

        $outsideStudent = User::factory()->student()->inProgress()->create();
        $outsideCoach = User::factory()->coach()->inProgress()->create();
        $this->createMeeting($outsideStudent, $outsideCoach, now()->addHour()->addMinutes(2)->startOfMinute());

        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->expectsOutput('面談リマインダー対象 1 件を処理しました。')
            ->assertExitCode(0);

        Bus::assertDispatchedTimes(SendMeetingReminderNotificationJob::class, 2);
        $this->assertDatabaseHas('meeting_reminder_deliveries', [
            'meeting_id' => $meeting->id,
            'recipient_user_id' => $student->id,
            'window' => 'one_hour_before',
        ]);
        $this->assertDatabaseHas('meeting_reminder_deliveries', [
            'meeting_id' => $meeting->id,
            'recipient_user_id' => $coach->id,
            'window' => 'one_hour_before',
        ]);
        $this->assertDatabaseMissing('meeting_reminder_deliveries', [
            'recipient_user_id' => $outsideStudent->id,
            'window' => 'one_hour_before',
        ]);
        $this->assertDatabaseMissing('meeting_reminder_deliveries', [
            'recipient_user_id' => $outsideCoach->id,
            'window' => 'one_hour_before',
        ]);
        $this->assertDatabaseCount('meeting_reminder_deliveries', 2);
    }

    public function test_canceled_and_completed_meetings_are_not_targeted(): void
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $canceledStudent = User::factory()->student()->inProgress()->create();
        $canceledCoach = User::factory()->coach()->inProgress()->create();
        $completedStudent = User::factory()->student()->inProgress()->create();
        $completedCoach = User::factory()->coach()->inProgress()->create();

        $this->createMeeting($canceledStudent, $canceledCoach, now()->addDay()->setTime(11, 0), MeetingStatus::Canceled);
        $this->createMeeting($completedStudent, $completedCoach, now()->addDay()->setTime(12, 0), MeetingStatus::Completed);

        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->expectsOutput('面談リマインダー対象 0 件を処理しました。')
            ->assertExitCode(0);

        Bus::assertNotDispatched(SendMeetingReminderNotificationJob::class);
        $this->assertDatabaseCount('meeting_reminder_deliveries', 0);
    }

    public function test_reminder_job_does_not_send_duplicate_reminders_when_rerun(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = $this->createMeeting($student, $coach, now()->addDay()->setTime(16, 0));

        DB::table('meeting_reminder_deliveries')->insert([
            'meeting_id' => $meeting->id,
            'recipient_user_id' => $student->id,
            'window' => 'eve',
            'delivered_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = new SendMeetingReminderNotificationJob((string) $meeting->id, (string) $student->id, 'eve');
        $job->handle();
        $job->handle();

        $this->assertCount(1, Notification::sent($student, BusinessEventNotification::class));
        Notification::assertNotSentTo($coach, BusinessEventNotification::class);
        $this->assertDatabaseHas('meeting_reminder_deliveries', [
            'meeting_id' => $meeting->id,
            'recipient_user_id' => $student->id,
            'window' => 'eve',
        ]);
        $this->assertNotNull(DB::table('meeting_reminder_deliveries')->where('meeting_id', $meeting->id)->value('delivered_at'));
    }

    public function test_recipients_that_cannot_receive_notifications_are_skipped(): void
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $student = User::factory()->student()->withdrawn()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = $this->createMeeting($student, $coach, now()->addDay()->setTime(11, 0));

        $this->artisan('notifications:send-meeting-reminders --window=eve')->assertExitCode(0);

        Bus::assertDispatchedTimes(SendMeetingReminderNotificationJob::class, 1);
        $this->assertDatabaseMissing('meeting_reminder_deliveries', [
            'meeting_id' => $meeting->id,
            'recipient_user_id' => $student->id,
            'window' => 'eve',
        ]);
        $this->assertDatabaseHas('meeting_reminder_deliveries', [
            'meeting_id' => $meeting->id,
            'recipient_user_id' => $coach->id,
            'window' => 'eve',
        ]);
        $this->assertDatabaseCount('meeting_reminder_deliveries', 1);
    }

    public function test_reminder_job_sends_notification_and_marks_delivery_as_delivered(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = $this->createMeeting($student, $coach, now()->addDay()->setTime(14, 0));

        DB::table('meeting_reminder_deliveries')->insert([
            'meeting_id' => $meeting->id,
            'recipient_user_id' => $student->id,
            'window' => 'eve',
            'delivered_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new SendMeetingReminderNotificationJob((string) $meeting->id, (string) $student->id, 'eve'))->handle();

        Notification::assertSentTo(
            $student,
            BusinessEventNotification::class,
            function (BusinessEventNotification $notification) use ($student, $meeting): bool {
                $data = $notification->toArray($student);

                return $data['notification_type'] === 'meeting_reminder_eve'
                    && $data['title'] === '面談リマインダー'
                    && $data['related_type'] === 'meeting'
                    && $data['related_id'] === (string) $meeting->id;
            },
        );
        $this->assertNotNull(DB::table('meeting_reminder_deliveries')->where('meeting_id', $meeting->id)->value('delivered_at'));
    }

    public function test_reminder_job_uses_notifications_queue_and_retry_backoff(): void
    {
        $job = new SendMeetingReminderNotificationJob(
            '01HN0000000000000000000000',
            '01HN0000000000000000000001',
            'eve',
        );

        $this->assertSame('notifications', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff());
    }

    public function test_reminder_job_skips_canceled_meeting(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = $this->createMeeting($student, $coach, now()->addDay()->setTime(14, 0), MeetingStatus::Canceled);

        DB::table('meeting_reminder_deliveries')->insert([
            'meeting_id' => $meeting->id,
            'recipient_user_id' => $student->id,
            'window' => 'eve',
            'delivered_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new SendMeetingReminderNotificationJob((string) $meeting->id, (string) $student->id, 'eve'))->handle();

        Notification::assertNothingSent();
        $this->assertNull(DB::table('meeting_reminder_deliveries')->where('meeting_id', $meeting->id)->value('delivered_at'));
    }

    public function test_command_rejects_invalid_window(): void
    {
        $this->artisan('notifications:send-meeting-reminders --window=invalid')
            ->expectsOutput('--window は eve または one_hour_before を指定してください。')
            ->assertExitCode(1);
    }

    private function createMeeting(
        User $student,
        User $coach,
        Carbon $scheduledAt,
        MeetingStatus $status = MeetingStatus::Reserved,
    ): Meeting {
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();

        return Meeting::factory()
            ->forEnrollment($enrollment)
            ->forStudent($student)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => $scheduledAt,
                'status' => $status->value,
            ]);
    }
}
