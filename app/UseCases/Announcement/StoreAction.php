<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendAnnouncementNotificationsJob;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
            $dispatchedCount = $this->recipientQuery($targetType, $validated)->count();

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
                'dispatched_count' => $dispatchedCount,
                'dispatched_at' => now(),
                'created_by' => $admin->id,
            ]);

            DB::afterCommit(fn () => SendAnnouncementNotificationsJob::dispatch((string) $announcement->id));

            return $announcement;
        });
    }

    /**
     * @param array<string, mixed> $validated
     *
     * @return Builder<User>
     */
    private function recipientQuery(AnnouncementTargetType $targetType, array $validated): Builder
    {
        $query = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);

        return match ($targetType) {
            AnnouncementTargetType::AllStudents => $query,

            AnnouncementTargetType::Certification => $query
                ->whereHas('enrollments', fn (Builder $q) => $q
                    ->where('certification_id', $validated['target_certification_id'])
                    ->where('status', EnrollmentStatus::Learning->value)
                ),

            AnnouncementTargetType::User => $query->whereKey($validated['target_user_id']),
        };
    }
}
