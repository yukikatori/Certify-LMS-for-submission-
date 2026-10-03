<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use App\Services\NotificationRecipientService;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Illuminate\Support\Facades\DB;

/**
 * 当事者(受講生 or コーチ)による面談キャンセルのユースケース。
 * reserved かつ開始前のみキャンセル可。消費済の面談回数 1 回分を返却する。
 */
final class CancelAction
{
    public function __construct(
        private RefundQuotaAction $refundAction,
        private NotificationRecipientService $notificationRecipients,
    ) {}

    public function __invoke(User $actor, Meeting $meeting): Meeting
    {
        return DB::transaction(function () use ($meeting, $actor) {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($locked->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $locked->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);

            ($this->refundAction)($locked->student, (string) $locked->id);

            DB::afterCommit(function () use ($locked, $actor): void {
                $locked->loadMissing(['student', 'coach', 'enrollment.certification']);

                $this->notifyMeetingCanceledRecipients($locked, $actor);
            });

            return $locked->fresh();
        });
    }

    private function notifyMeetingCanceledRecipients(Meeting $meeting, User $actor): void
    {
        $scheduledAt = $meeting->scheduled_at->format('Y/m/d H:i');
        $certificationName = $meeting->enrollment?->certification?->name ?? '受講資格';

        foreach ([$meeting->student, $meeting->coach] as $recipient) {
            if ($recipient === null) {
                continue;
            }

            if ((string) $recipient->id === (string) $actor->id) {
                continue;
            }

            if (! $this->notificationRecipients->canReceive($recipient)) {
                continue;
            }

            $recipient->notify(new BusinessEventNotification([
                'notification_type' => 'meeting_canceled',
                'title' => '面談がキャンセルされました',
                'message' => $actor->name.'さんが '.$scheduledAt.' の'.$certificationName.'面談をキャンセルしました。',
                'body_preview' => mb_strimwidth($meeting->topic ?? '', 0, 120, '...'),
                'action_url' => route('meetings.show', $meeting),
                'related_type' => 'meeting',
                'related_id' => (string) $meeting->id,
            ]));
        }
    }
}
