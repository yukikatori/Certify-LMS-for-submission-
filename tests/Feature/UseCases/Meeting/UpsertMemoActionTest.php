<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\Models\User;
use App\UseCases\Meeting\UpsertMemoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertMemoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_memo_for_reserved_meeting(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        $memo = app(UpsertMemoAction::class)($meeting, [
            'body' => '初回面談メモ',
        ]);

        $this->assertTrue($memo->exists);
        $this->assertSame($meeting->id, $memo->meeting_id);
        $this->assertSame('初回面談メモ', $memo->body);
    }

    public function test_updates_existing_memo(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        $existingMemo = MeetingMemo::factory()->forMeeting($meeting)->create([
            'body' => '更新前メモ',
        ]);

        $modifiedMemo = app(UpsertMemoAction::class)($meeting, [
            'body' => '面談メモ更新',
        ]);

        $this->assertTrue($modifiedMemo->exists);
        $this->assertSame($existingMemo->id, $modifiedMemo->id);
        $this->assertSame($meeting->id, $modifiedMemo->meeting_id);
        $this->assertSame('面談メモ更新', $modifiedMemo->body);

        $this->assertSame(1, MeetingMemo::query()->where('meeting_id', $meeting->id)->count());
    }

    public function test_creates_memo_for_completed_meeting(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create();

        $memo = app(UpsertMemoAction::class)($meeting, [
            'body' => '初回面談メモ',
        ]);

        $this->assertTrue($memo->exists);
        $this->assertSame($meeting->id, $memo->meeting_id);
        $this->assertSame('初回面談メモ', $memo->body);
    }

    public function test_throws_when_meeting_is_canceled(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $meeting = Meeting::factory()->canceled()->forCoach($coach)->forStudent($student)->create();

        $this->expectException(MeetingStatusTransitionException::class);

        app(UpsertMemoAction::class)($meeting, [
            'body' => '初回面談メモ',
        ]);
    }
}
