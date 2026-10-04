<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * お知らせを新規作成するユースケース。
 */
final class StoreAction
{
    /**
     * @param array{
     *     title: string,
     *     body: string,
     *     target_type: string,
     *     target_certification_id?: string|null,
     *     target_user_id?: string|null,
     * } $validated
     */
    public function __invoke(User $admin, array $validated): Announcement
    {
        return DB::transaction(function () use ($admin, $validated): Announcement {
            $targetType = AnnouncementTargetType::from($validated['target_type']);
            $recipients = $this->resolveRecipients($targetType, $validated);

            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $targetType,
                'target_certification_id' => $targetType === AnnouncementTargetType::Certification
                    ? $validated['target_certification_id']
                    : null,
                'target_user_id' => $targetType === AnnouncementTargetType::User
                    ? $validated['target_user_id']
                    : null,
                'dispatched_count' => $recipients->count(),
                'dispatched_at' => now(),
                'created_by' => $admin->id,
            ]);

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

            return $announcement;
        });
    }

    /**
     * @param array<string, mixed> $validated
     *
     * @return Collection<int, User>
     */
    private function resolveRecipients(AnnouncementTargetType $targetType, array $validated): Collection
    {
        return match ($targetType) {
            AnnouncementTargetType::AllStudents => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->get(),

            AnnouncementTargetType::Certification => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->whereHas('enrollments', fn ($q) => $q
                    ->where('certification_id', $validated['target_certification_id'])
                    ->where('status', EnrollmentStatus::Learning->value)
                )
                ->get(),

            AnnouncementTargetType::User => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->whereKey($validated['target_user_id'])
                ->get(),
        };
    }
}
