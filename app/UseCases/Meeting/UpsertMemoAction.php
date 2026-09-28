<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use Illuminate\Support\Facades\DB;

/**
 * 担当コーチによる面談メモ作成・更新のユースケース。
 * canceled の面談にはメモを残せない。
 */
final class UpsertMemoAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(Meeting $meeting, array $validated): MeetingMemo
    {
        return DB::transaction(function () use ($meeting, $validated) {
            if (! in_array($meeting->status, [MeetingStatus::Reserved, MeetingStatus::Completed], true)) {
                throw MeetingStatusTransitionException::forMemo();
            }

            return MeetingMemo::updateOrCreate(
                ['meeting_id' => $meeting->id],
                ['body' => $validated['body']],
            );
        });
    }
}
