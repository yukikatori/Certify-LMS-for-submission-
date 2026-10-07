<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * 面談リマインダー配信 Job。
 *
 * リマインダー配信履歴をロックし、未配信の場合のみ通知送信と
 * delivered_at 更新を worker 内でまとめて処理する。
 */
final class SendMeetingReminderNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly string $meetingId,
        private readonly string $recipientId,
        private readonly string $window,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            $delivery = DB::table('meeting_reminder_deliveries')
                ->where('meeting_id', $this->meetingId)
                ->where('recipient_user_id', $this->recipientId)
                ->where('window', $this->window)
                ->lockForUpdate()
                ->first();

            if ($delivery === null || $delivery->delivered_at !== null) {
                return;
            }

            $meeting = Meeting::query()
                ->with(['student', 'coach', 'enrollment.certification'])
                ->findOrFail($this->meetingId);

            if ($meeting->status !== MeetingStatus::Reserved) {
                return;
            }

            $recipient = User::query()->findOrFail($this->recipientId);

            Notification::sendNow($recipient, new BusinessEventNotification([
                'notification_type' => 'meeting_reminder_'.$this->window,
                'title' => '面談リマインダー',
                'message' => $this->message($meeting),
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

    private function message(Meeting $meeting): string
    {
        $scheduledAt = $meeting->scheduled_at->format('Y/m/d H:i');
        $certificationName = $meeting->enrollment?->certification?->name ?? '受講資格';

        $label = match ($this->window) {
            'eve' => '明日',
            'one_hour_before' => '1時間後',
        };

        return "{$certificationName}の面談が{$label} ({$scheduledAt}) に予定されています。";
    }
}
