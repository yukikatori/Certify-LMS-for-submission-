<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\GoogleCalendarConnection;
use App\Models\Meeting;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\UseCases\Meeting\CancelAction;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('external-api')]
class GoogleCalendarMockedFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_excludes_busy_slots_from_stubbed_calendar_operation(): void
    {
        [$certification, $coach] = $this->certificationWithCoach();
        GoogleCalendarConnection::factory()->forCoach($coach)->create();
        CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '12:00:00')->create();

        $calendar = Mockery::mock(GoogleCalendarService::class);
        $calendar->shouldReceive('fetchBusyPeriods')
            ->once()
            ->andReturn([[
                'start' => CarbonImmutable::parse('2026-06-01 10:00:00', 'Asia/Tokyo'),
                'end' => CarbonImmutable::parse('2026-06-01 11:00:00', 'Asia/Tokyo'),
            ]]);
        $this->app->instance(GoogleCalendarService::class, $calendar);

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification(
            $certification,
            Carbon::parse('2026-06-01', 'Asia/Tokyo'),
        );

        $this->assertSame(['09:00', '11:00'], $slots->map(fn (array $slot): string => $slot['slot_start']->format('H:i'))->all());
    }

    public function test_availability_falls_back_to_local_slots_when_calendar_fetch_fails(): void
    {
        [$certification, $coach] = $this->certificationWithCoach();
        GoogleCalendarConnection::factory()->forCoach($coach)->create();
        CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '11:00:00')->create();

        $calendar = Mockery::mock(GoogleCalendarService::class);
        $calendar->shouldReceive('fetchBusyPeriods')
            ->once()
            ->andThrow(new RuntimeException('Google Calendar freeBusy failed'));
        $this->app->instance(GoogleCalendarService::class, $calendar);

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification(
            $certification,
            Carbon::parse('2026-06-01', 'Asia/Tokyo'),
        );

        $this->assertSame(['09:00', '10:00'], $slots->map(fn (array $slot): string => $slot['slot_start']->format('H:i'))->all());
    }

    public function test_reservation_creates_calendar_event_through_stubbed_operation(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        [$certification, $coach] = $this->certificationWithCoach([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        GoogleCalendarConnection::factory()->forCoach($coach)->create();
        CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $calendar = Mockery::mock(GoogleCalendarService::class);
        $calendar->shouldReceive('fetchBusyPeriods')->andReturn([]);
        $calendar->shouldReceive('createEvent')
            ->once()
            ->with(
                Mockery::type(GoogleCalendarConnection::class),
                'LMS面談',
                Mockery::on(fn ($value): bool => $value->equalTo($scheduledAt)),
                Mockery::on(fn ($value): bool => $value->equalTo($scheduledAt->copy()->addHour())),
                Mockery::on(fn (string $description): bool => str_contains($description, '受講生: '.$student->name)
                    && str_contains($description, '相談内容: 学習計画について相談したい')),
                'https://meet.example.com/coach-room',
            )
            ->andReturn('google-event-123');
        $this->app->instance(GoogleCalendarService::class, $calendar);

        $meeting = app(StoreAction::class)($student, $enrollment, [
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'topic' => '学習計画について相談したい',
        ]);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'google_calendar_event_id' => 'google-event-123',
        ]);
    }

    public function test_calendar_creation_failure_does_not_cancel_reservation(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        [$certification, $coach] = $this->certificationWithCoach();
        GoogleCalendarConnection::factory()->forCoach($coach)->create();
        CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $calendar = Mockery::mock(GoogleCalendarService::class);
        $calendar->shouldReceive('fetchBusyPeriods')->andReturn([]);
        $calendar->shouldReceive('createEvent')
            ->once()
            ->andThrow(new RuntimeException('Google Calendar event creation failed'));
        $this->app->instance(GoogleCalendarService::class, $calendar);

        $meeting = app(StoreAction::class)($student, $enrollment, [
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'topic' => '学習計画について相談したい',
        ]);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'google_calendar_event_id' => null,
        ]);
    }

    public function test_cancel_deletes_calendar_event_through_stubbed_operation(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create();
        GoogleCalendarConnection::factory()->forCoach($coach)->create();
        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
                'google_calendar_event_id' => 'google-event-123',
            ]);
        MeetingQuotaTransaction::factory()->consumed()->create([
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
        ]);

        $calendar = Mockery::mock(GoogleCalendarService::class);
        $calendar->shouldReceive('deleteEvent')
            ->once()
            ->with(Mockery::type(GoogleCalendarConnection::class), 'google-event-123');
        $this->app->instance(GoogleCalendarService::class, $calendar);

        app(CancelAction::class)($student, $meeting);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'google_calendar_event_id' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $coachAttributes
     * @return array{Certification, User}
     */
    private function certificationWithCoach(array $coachAttributes = []): array
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create($coachAttributes);
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        return [$certification, $coach];
    }
}
