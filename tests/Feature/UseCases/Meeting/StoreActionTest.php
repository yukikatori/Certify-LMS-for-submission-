<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingQuotaService;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_reserved_meeting(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0); // 次の月曜 10:00(未来)

        $meeting = app(StoreAction::class)($student, $enrollment, [
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'topic' => '学習計画について相談したい',
        ]);

        $this->assertTrue($meeting->exists);
        $this->assertSame($student->id, $meeting->student_id);
        $this->assertSame($coach->id, $meeting->coach_id);
        $this->assertSame($enrollment->id, $meeting->enrollment_id);
        $this->assertSame(MeetingStatus::Reserved, $meeting->status);
        $this->assertSame('学習計画について相談したい', $meeting->topic);
        $this->assertSame('https://meet.example.com/coach-room', $meeting->meeting_url_snapshot);
    }

    public function test_consumes_meeting_quota_and_links_transaction(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0); // 次の月曜 10:00(未来)

        $meeting = app(StoreAction::class)($student, $enrollment, [
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'topic' => '学習計画について相談したい',
        ]);

        $this->assertSame(2, app(MeetingQuotaService::class)->remaining($student));

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
            'related_meeting_id' => $meeting->id,
        ]);
    }

    public function test_assigns_least_loaded_available_coach(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();

        $busyCoach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/busy-coach',
        ]);

        $leastLoadedCoach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/least-loaded-coach',
        ]);

        $certification = Certification::factory()->published()->create();

        foreach ([$busyCoach, $leastLoadedCoach] as $coach) {
            $certification->coaches()->attach($coach->id, [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
                'unassigned_at' => null,
            ]);

            CoachAvailability::factory()
                ->forCoach($coach)
                ->onDay(Carbon::MONDAY)
                ->timeRange('09:00:00', '18:00:00')
                ->create();
        }

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        Meeting::factory()
            ->completed()
            ->forCoach($busyCoach)
            ->forStudent($student)
            ->forEnrollment($enrollment)
            ->create([
                'scheduled_at' => now()->subDays(3)->startOfHour(),
            ]);

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $meeting = app(StoreAction::class)($student, $enrollment, [
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'topic' => '学習計画について相談したい',
        ]);

        $this->assertSame($leastLoadedCoach->id, $meeting->coach_id);
        $this->assertSame('https://meet.example.com/least-loaded-coach', $meeting->meeting_url_snapshot);
    }

    public function test_throws_when_meeting_quota_is_insufficient(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 0]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0); // 次の月曜 10:00(未来)

        $this->expectException(InsufficientMeetingQuotaException::class);

        app(StoreAction::class)($student, $enrollment, [
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'topic' => '学習計画について相談したい',
        ]);
    }

    public function test_throws_when_slot_is_out_of_availability(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0); // 次の月曜 10:00(未来)

        $this->expectException(MeetingOutOfAvailabilityException::class);

        app(StoreAction::class)($student, $enrollment, [
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'topic' => '学習計画について相談したい',
        ]);
    }

    public function test_throws_when_slot_is_already_booked(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $otherStudent = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();

        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $otherEnrollment = Enrollment::factory()
            ->for($otherStudent, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($otherStudent)
            ->forEnrollment($otherEnrollment)
            ->create([
                'scheduled_at' => $scheduledAt,
            ]);

        $this->expectException(MeetingOutOfAvailabilityException::class);

        app(StoreAction::class)($student, $enrollment, [
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'topic' => '学習計画について相談したい',
        ]);
    }

    public function test_sends_database_notification_to_student_and_coach_when_meeting_is_reserved(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $meeting = app(StoreAction::class)($student, $enrollment, [
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'topic' => '学習計画について相談したい',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $student->getMorphClass(),
            'notifiable_id' => $student->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $coach->getMorphClass(),
            'notifiable_id' => $coach->id,
        ]);

        $studentNotification = $student->notifications()->first();
        $coachNotification = $coach->notifications()->first();

        $this->assertNotNull($studentNotification);
        $this->assertNotNull($coachNotification);

        foreach ([$studentNotification, $coachNotification] as $notification) {
            $this->assertSame('meeting_reserved', $notification->data['notification_type']);
            $this->assertSame('meeting', $notification->data['related_type']);
            $this->assertSame((string) $meeting->id, $notification->data['related_id']);
            $this->assertSame(route('meetings.show', $meeting), $notification->data['action_url']);
        }
    }

    private function attachCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }
}
