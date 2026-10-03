<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use App\Services\CoachMeetingLoadService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\Services\NotificationRecipientService;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 受講生の予約申請のユースケース。
 * 残面談回数を確認し、空き枠から過去実績最少のコーチを自動割当して reserved で確定する。
 * 同時刻 race condition はUNIQUE 違反として検知し 409 へ変換する。
 */
final class StoreAction
{
    public function __construct(
        private MeetingAvailabilityService $availabilityService,
        private CoachMeetingLoadService $coachLoadService,
        private MeetingQuotaService $quotaService,
        private ConsumeQuotaAction $consumeAction,
        private NotificationRecipientService $notificationRecipients,
    ) {}

    /**
     * @param array{scheduled_at: string, topic: string} $validated
     */
    public function __invoke(User $student, Enrollment $enrollment, array $validated): Meeting
    {
        $scheduledAt = Carbon::parse($validated['scheduled_at']);
        $topic = $validated['topic'];

        return DB::transaction(function () use ($student, $enrollment, $scheduledAt, $topic) {

            // 残面談回数チェック
            if ($this->quotaService->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            // 空き枠チェック
            $this->availabilityService->validateSlot($enrollment->certification, $scheduledAt);

            // コーチ候補取得
            $candidates = $this->findAvailableCoaches($enrollment->certification, $scheduledAt);
            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }

            // 過去実績最少のコーチを選ぶ
            $coach = $this->coachLoadService->leastLoadedCoach($candidates);

            // 予約作成
            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id,
                    'coach_id' => $coach->id,
                    'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => MeetingStatus::Reserved->value,
                    'topic' => $topic,
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                throw new MeetingNoAvailableCoachException($e);
            }

            // 面談回数を消費
            $transaction = ($this->consumeAction)($student, $meeting->id);
            $meeting->update(['meeting_quota_transaction_id' => $transaction->id]);

            DB::afterCommit(function () use ($meeting): void {
                $meeting->loadMissing(['student', 'coach', 'enrollment.certification']);

                $this->notifyMeetingReservedRecipients($meeting);
            });

            return $meeting->fresh();
        });
    }

    private function notifyMeetingReservedRecipients(Meeting $meeting): void
    {
        $scheduledAt = $meeting->scheduled_at->format('Y/m/d H:i');
        $certificationName = $meeting->enrollment?->certification?->name ?? '受講資格';

        foreach ([$meeting->student, $meeting->coach] as $recipient) {
            if ($recipient === null) {
                continue;
            }

            if (! $this->notificationRecipients->canReceive($recipient)) {
                continue;
            }

            $recipient->notify(new BusinessEventNotification([
                'notification_type' => 'meeting_reserved',
                'title' => '面談が予約されました',
                'message' => $certificationName.'の面談が '.$scheduledAt.' に予約されました。',
                'body_preview' => mb_strimwidth($meeting->topic ?? '', 0, 120, '...'),
                'action_url' => route('meetings.show', $meeting),
                'related_type' => 'meeting',
                'related_id' => (string) $meeting->id,
            ]));
        }
    }

    /**
     * 担当コーチ集合のうち、(1) 当該時刻に有効な availability 枠があり、
     * (2) 当該時刻に reserved / completed の Meeting を持たないコーチ集合を返す。
     *
     * @return Collection<int, User>
     */
    private function findAvailableCoaches(Certification $certification, Carbon $scheduledAt): Collection
    {
        $time = $scheduledAt->format('H:i:s');

        return $certification->coaches()
            ->whereHas('coachAvailabilities', function ($q) use ($scheduledAt, $time) {
                $q->where('day_of_week', $scheduledAt->dayOfWeek)
                    ->where('is_active', true)
                    ->where('start_time', '<=', $time)
                    ->where('end_time', '>', $time);
            })
            ->whereDoesntHave('meetingsAsCoach', function ($q) use ($scheduledAt) {
                $q->where('scheduled_at', $scheduledAt)
                    ->whereIn('status', [
                        MeetingStatus::Reserved->value,
                        MeetingStatus::Completed->value,
                    ]);
            })
            ->get();
    }
}
