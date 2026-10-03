<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use App\Services\MeetingQuotaService;
use App\UseCases\Meeting\CancelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_reserved_meeting(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        app(CancelAction::class)($student, $meeting);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::Canceled->value,
            'canceled_by_user_id' => $student->id,
        ]);

        $this->assertNotNull($meeting->fresh()->canceled_at);
    }

    public function test_refunds_meeting_quota_when_canceling_meeting(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        MeetingQuotaTransaction::factory()->create([
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
            'related_meeting_id' => $meeting->id,
        ]);

        $this->assertSame(2, app(MeetingQuotaService::class)->remaining($student));

        app(CancelAction::class)($student, $meeting);

        $this->assertSame(3, app(MeetingQuotaService::class)->remaining($student));

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
            'related_meeting_id' => $meeting->id,
        ]);
    }

    public function test_throws_when_meeting_is_completed(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(3)->startOfHour(),
        ]);

        $this->expectException(MeetingStatusTransitionException::class);

        app(CancelAction::class)($student, $meeting);
    }

    public function test_throws_when_meeting_is_already_canceled(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->canceled()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $this->expectException(MeetingStatusTransitionException::class);

        app(CancelAction::class)($student, $meeting);
    }

    public function test_throws_when_meeting_has_already_started(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subMinute(),
        ]);

        $this->expectException(MeetingAlreadyStartedException::class);

        app(CancelAction::class)($student, $meeting);
    }

    public function test_sends_database_notification_to_other_party_when_meeting_is_canceled(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $meeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
                'topic' => '学習計画について相談したい',
            ]);

        app(CancelAction::class)($student, $meeting);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => $student->getMorphClass(),
            'notifiable_id' => $student->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $coach->getMorphClass(),
            'notifiable_id' => $coach->id,
        ]);

        $notification = $coach->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('meeting_canceled', $notification->data['notification_type']);
        $this->assertSame('meeting', $notification->data['related_type']);
        $this->assertSame((string) $meeting->id, $notification->data['related_id']);
        $this->assertSame(route('meetings.show', $meeting), $notification->data['action_url']);
    }
}
