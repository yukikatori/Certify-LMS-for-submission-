<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
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

                $delivery = DB::table('meeting_reminder_deliveries')
                    ->where('meeting_id', $meeting->id)
                    ->where('recipient_user_id', $recipient->id)
                    ->where('window', $window)
                    ->lockForUpdate()
                    ->first();
                
                if ($delivery === null || $delivery->delivered_at !== null) {
                    return;
                }

                $recipient->notify(new BusinessEventNotification([
                    'notification_type' => 'meeting_reminder_'.$window,
                    'title' => '面談リマインダー',
                    'message' => $this->message($meeting, $window),
                    'body_preview' => mb_strimwidth($meeting->topic ?? '', 0, 120, '...'),
                    'action_url' => route('meetings.show', $meeting),
                    'related_type' => 'meeting',
                    'related_id' => (string) $meeting->id,
                ]));

                DB::table('meeting_reminder_deliveries')
                    ->where('id', $delivery->id)
                    ->update([
                        'delivered_at' => now(),
                        'updated_at' => now(),
                    ]);
            });
        }
    }

    private function message(Meeting $meeting, string $window): string
    {
        $scheduledAt = $meeting->scheduled_at->format('Y/m/d H:i');
        $certificationName = $meeting->enrollment?->certification?->name ?? '受講資格';

        $label = match ($window) {
            'eve' => '明日',
            'one_hour_before' => '1時間後',
        };

        return "{$certificationName}の面談が{$label} ({$scheduledAt}) に予定されています。";
    }
}
