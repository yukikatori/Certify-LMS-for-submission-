<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Jobs\SendMeetingReminderNotificationJob;
use App\Models\Meeting;
use App\Models\User;
use App\Services\NotificationRecipientService;
use Illuminate\Support\Facades\DB;

/**
 * Schedule Command から呼ばれる面談リマインダーのユースケース。
 * 前日・1時間前の 2 タイミングで自動リマインダー通知を配信する。
 */
final class SendMeetingReminderAction
{
    public function __construct(
        private readonly NotificationRecipientService $notificationRecipients,
    ) {}

    public function __invoke(Meeting $meeting, string $window): void
    {
        $meeting->loadMissing(['student', 'coach', 'enrollment.certification']);

        if ($meeting->status !== MeetingStatus::Reserved) {
            return;
        }

        foreach ([$meeting->student, $meeting->coach] as $recipient) {
            if (! $recipient instanceof User) {
                continue;
            }

            if (! $this->notificationRecipients->canReceive($recipient)) {
                continue;
            }

            DB::transaction(function () use ($meeting, $recipient, $window): void {
                DB::table('meeting_reminder_deliveries')->insertOrIgnore([
                    'meeting_id' => $meeting->id,
                    'recipient_user_id' => $recipient->id,
                    'window' => $window,
                    'delivered_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                SendMeetingReminderNotificationJob::dispatch(
                    (string) $meeting->id,
                    (string) $recipient->id,
                    $window,
                );
            });
        }
    }
}
