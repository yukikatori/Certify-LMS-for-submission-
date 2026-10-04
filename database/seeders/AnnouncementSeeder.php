<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\CertificationStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 管理者お知らせ配信 Feature のデモデータシーダー。
 *
 * 配信対象 3 種類(全受講生 / 資格指定 / ユーザー指定)の配信履歴と、
 * 対象受講生に紐づく通知を投入する。初期データなのでメール送信は行わず、
 * database notification のみを作成する。
 *
 * 依存: UserSeeder → CertificationSeeder → EnrollmentSeeder の後に走る前提。
 */
final class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('created_at')
            ->first();

        if ($admin === null) {
            $this->command?->warn('AnnouncementSeeder: 管理者が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $this->seedAllStudentsAnnouncement($admin);
        $this->seedCertificationAnnouncement($admin);
        $this->seedUserAnnouncement($admin);
    }

    private function seedAllStudentsAnnouncement(User $admin): void
    {
        $this->createAnnouncementWithNotifications(
            admin: $admin,
            recipients: $this->activeStudents(),
            attributes: [
                'title' => '【重要】メンテナンス実施のお知らせ',
                'body' => "以下の日程で Certify LMS のメンテナンスを実施します。\n\n実施日時: 2026/10/10 22:00 - 23:00\n影響範囲: 学習画面、模擬試験、面談予約\n\n作業時間中は一時的にアクセスしづらくなる場合があります。学習記録の保存後にログアウトしてください。",
                'target_type' => AnnouncementTargetType::AllStudents,
                'target_certification_id' => null,
                'target_user_id' => null,
                'dispatched_at' => now()->subDays(3)->setTime(10, 0),
            ],
        );
    }

    private function seedCertificationAnnouncement(User $admin): void
    {
        $certification = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->whereHas('enrollments', fn ($q) => $q
                ->where('status', EnrollmentStatus::Learning->value)
                ->whereHas('user', fn ($userQuery) => $userQuery
                    ->where('role', UserRole::Student->value)
                    ->where('status', UserStatus::InProgress->value)
                )
            )
            ->orderBy('created_at')
            ->first();

        if ($certification === null) {
            $this->command?->warn('AnnouncementSeeder: 資格指定お知らせの対象資格が見つかりませんでした。');

            return;
        }

        $recipients = $this->activeStudents()
            ->filter(fn (User $student): bool => $student->enrollments()
                ->where('certification_id', $certification->id)
                ->where('status', EnrollmentStatus::Learning->value)
                ->exists())
            ->values();

        $this->createAnnouncementWithNotifications(
            admin: $admin,
            recipients: $recipients,
            attributes: [
                'title' => '教材更新のお知らせ: '.$certification->name,
                'body' => "{$certification->name} の教材を更新しました。\n\n変更内容:\n- 直近の出題傾向に合わせて演習問題を追加\n- 解説文の表現を一部調整\n- 学習順序の案内を更新\n\n次回学習時に該当パートをご確認ください。",
                'target_type' => AnnouncementTargetType::Certification,
                'target_certification_id' => $certification->id,
                'target_user_id' => null,
                'dispatched_at' => now()->subDays(2)->setTime(14, 30),
            ],
        );
    }

    private function seedUserAnnouncement(User $admin): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->first()
            ?? $this->activeStudents()->first();

        if ($student === null) {
            $this->command?->warn('AnnouncementSeeder: ユーザー指定お知らせの対象受講生が見つかりませんでした。');

            return;
        }

        $this->createAnnouncementWithNotifications(
            admin: $admin,
            recipients: new Collection([$student]),
            attributes: [
                'title' => '学習フォローのお知らせ',
                'body' => "{$student->name} さん\n\nここまでの学習おつかれさまです。次回の学習では、直近で正答率が下がっているカテゴリを中心に復習すると効果的です。\n\n必要に応じて面談予約や質問掲示板も活用してください。",
                'target_type' => AnnouncementTargetType::User,
                'target_certification_id' => null,
                'target_user_id' => $student->id,
                'dispatched_at' => now()->subDay()->setTime(9, 15),
            ],
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function activeStudents(): Collection
    {
        return User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param Collection<int, User> $recipients
     * @param array{
     *     title: string,
     *     body: string,
     *     target_type: AnnouncementTargetType,
     *     target_certification_id: string|null,
     *     target_user_id: string|null,
     *     dispatched_at: Carbon,
     * } $attributes
     */
    private function createAnnouncementWithNotifications(User $admin, Collection $recipients, array $attributes): void
    {
        $announcement = new Announcement;
        $announcement->forceFill([
            'title' => $attributes['title'],
            'body' => $attributes['body'],
            'target_type' => $attributes['target_type']->value,
            'target_certification_id' => $attributes['target_certification_id'],
            'target_user_id' => $attributes['target_user_id'],
            'dispatched_count' => $recipients->count(),
            'dispatched_at' => $attributes['dispatched_at'],
            'created_by' => $admin->id,
            'created_at' => $attributes['dispatched_at'],
            'updated_at' => $attributes['dispatched_at'],
        ]);
        $announcement->save();

        foreach ($recipients as $index => $recipient) {
            $createdAt = $attributes['dispatched_at']->copy()->addSeconds($index);

            $recipient->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'database-seed',
                'data' => [
                    'notification_type' => 'admin_announcement',
                    'title' => $announcement->title,
                    'message' => '運営からのお知らせがあります。',
                    'body' => $announcement->body,
                    'body_preview' => mb_strimwidth($announcement->body, 0, 120, '...'),
                    'related_type' => 'announcement',
                    'related_id' => (string) $announcement->id,
                ],
                'read_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
