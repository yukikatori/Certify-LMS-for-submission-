<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 管理者お知らせの配信 Job。
 *
 * HTTP リクエスト内ではお知らせ作成だけを行い、
 * 実際の対象受講生への通知送信はこの Job がバックグラウンドで処理する。
 */
final class SendAnnouncementNotificationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly string $announcementId,
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
        $announcement = Announcement::query()->findOrFail($this->announcementId);

        $this->recipientQuery($announcement)
            ->chunkById(100, function (Collection $recipients) use ($announcement): void {
                foreach ($recipients as $recipient) {
                    $recipient->notify(new BusinessEventNotification([
                        'notification_type' => 'admin_announcement',
                        'title' => $announcement->title,
                        'message' => '運営からのお知らせがあります。',
                        'body' => $announcement->body,
                        'body_preview' => mb_strimwidth($announcement->body, 0, 120, '...'),
                        'related_type' => 'announcement',
                        'related_id' => (string) $announcement->id,
                    ]));
                }
            });
    }

    /**
     * @return Builder<User>
     */
    private function recipientQuery(Announcement $announcement): Builder
    {
        $query = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);

        return match ($announcement->target_type) {
            AnnouncementTargetType::AllStudents => $query,

            AnnouncementTargetType::Certification => $query->whereHas('enrollments', fn (Builder $q) => $q
                ->where('certification_id', $announcement->target_certification_id)
                ->where('status', EnrollmentStatus::Learning->value)
            ),

            AnnouncementTargetType::User => $query->whereKey($announcement->target_user_id),
        };
    }
}
