<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\MeetingStatus;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_window_sends_reminders_to_reserved_meeting_participants(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = $this->createMeeting($student, $coach, now()->addDay()->setTime(14, 0));

        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->expectsOutput('面談リマインダー対象 1 件を処理しました。')
            ->assertExitCode(0);

        Notification::assertSentTo($student, BusinessEventNotification::class);
        Notification::assertSentTo($coach, BusinessEventNotification::class);
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

    public function test_one_hour_before_window_sends_reminders_only_for_next_minute(): void
    {
        Notification::fake();
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

        Notification::assertSentTo($student, BusinessEventNotification::class);
        Notification::assertSentTo($coach, BusinessEventNotification::class);
        Notification::assertNotSentTo($outsideStudent, BusinessEventNotification::class);
        Notification::assertNotSentTo($outsideCoach, BusinessEventNotification::class);
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
        $this->assertDatabaseCount('meeting_reminder_deliveries', 2);
    }

    public function test_canceled_and_completed_meetings_are_not_targeted(): void
    {
        Notification::fake();
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

        Notification::assertNothingSent();
        $this->assertDatabaseCount('meeting_reminder_deliveries', 0);
    }

    public function test_command_does_not_send_duplicate_reminders_when_rerun(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $this->createMeeting($student, $coach, now()->addDay()->setTime(16, 0));

        $this->artisan('notifications:send-meeting-reminders --window=eve')->assertExitCode(0);
        $this->artisan('notifications:send-meeting-reminders --window=eve')->assertExitCode(0);

        $this->assertCount(1, Notification::sent($student, BusinessEventNotification::class));
        $this->assertCount(1, Notification::sent($coach, BusinessEventNotification::class));
        $this->assertDatabaseCount('meeting_reminder_deliveries', 2);
    }

    public function test_recipients_that_cannot_receive_notifications_are_skipped(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $student = User::factory()->student()->withdrawn()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = $this->createMeeting($student, $coach, now()->addDay()->setTime(11, 0));

        $this->artisan('notifications:send-meeting-reminders --window=eve')->assertExitCode(0);

        Notification::assertNotSentTo($student, BusinessEventNotification::class);
        Notification::assertSentTo($coach, BusinessEventNotification::class);
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
