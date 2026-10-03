<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChatRoom;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 通知 Feature のデモデータシーダー。
 *
 * 固定の受講生 / コーチに既読・未読混在の通知を投入し、一覧・未読タブ・ページネーション・
 * 通知クリック時の遷移先確認ができる状態を作る。
 */
final class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        $coach = User::query()->where('email', 'coach@certify-lms.test')->first();

        if ($student !== null) {
            $this->seedStudentNotifications($student);
        }

        if ($coach !== null) {
            $this->seedCoachNotifications($coach);
        }
    }

    private function seedStudentNotifications(User $student): void
    {
        $chatRoom = ChatRoom::query()
            ->whereHas('enrollment', fn ($q) => $q->where('user_id', $student->id))
            ->orderByDesc('last_message_at')
            ->first();

        $qaThread = QaThread::query()
            ->where('user_id', $student->id)
            ->latest()
            ->first();

        $meeting = Meeting::query()
            ->where('student_id', $student->id)
            ->latest('scheduled_at')
            ->first();

        $samples = [
            [
                'notification_type' => 'chat_message_received',
                'title' => 'chat に新着メッセージがあります',
                'message' => 'コーチ太郎さんから学習計画について返信が届きました。',
                'action_url' => $chatRoom ? route('chat.show', $chatRoom) : route('chat.index'),
            ],
            [
                'notification_type' => 'qa_reply_received',
                'title' => '質問掲示板に返信があります',
                'message' => '投稿した質問に返信が届きました。',
                'action_url' => $qaThread ? route('qa-board.show', $qaThread) : route('qa-board.index'),
            ],
            [
                'notification_type' => 'meeting_reserved',
                'title' => '面談が予約されました',
                'message' => '次回面談の予約内容を確認できます。',
                'action_url' => $meeting ? route('meetings.show', $meeting) : route('meetings.index'),
            ],
            [
                'notification_type' => 'meeting_canceled',
                'title' => '面談がキャンセルされました',
                'message' => 'キャンセルされた面談の詳細を確認できます。',
                'action_url' => route('meetings.index'),
            ],
        ];

        for ($i = 0; $i < 24; $i++) {
            $this->createNotification(
                user: $student,
                data: $samples[$i % count($samples)],
                createdAt: Carbon::now()->subHours($i + 1),
                read: $i % 3 === 0,
            );
        }
    }

    private function seedCoachNotifications(User $coach): void
    {
        $chatRoom = ChatRoom::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', $coach->id))
            ->orderByDesc('last_message_at')
            ->first();

        $qaThread = QaThread::query()
            ->whereHas('certification.coaches', fn ($q) => $q->where('users.id', $coach->id))
            ->latest()
            ->first();

        $meeting = Meeting::query()
            ->where('coach_id', $coach->id)
            ->latest('scheduled_at')
            ->first();

        $samples = [
            [
                'notification_type' => 'chat_message_received',
                'title' => '担当受講生からchatが届きました',
                'message' => '受講生から相談メッセージが届いています。',
                'action_url' => $chatRoom ? route('chat.show', $chatRoom) : route('coach.chat.index'),
            ],
            [
                'notification_type' => 'qa_reply_received',
                'title' => '質問掲示板に返信があります',
                'message' => '担当資格の質問に返信がありました。',
                'action_url' => $qaThread ? route('qa-board.show', $qaThread) : route('qa-board.index'),
            ],
            [
                'notification_type' => 'meeting_reserved',
                'title' => '面談が予約されました',
                'message' => '担当受講生との面談が予約されました。',
                'action_url' => $meeting ? route('meetings.show', $meeting) : route('coach.meetings.index'),
            ],
        ];

        for ($i = 0; $i < 10; $i++) {
            $this->createNotification(
                user: $coach,
                data: $samples[$i % count($samples)],
                createdAt: Carbon::now()->subMinutes(45 * ($i + 1)),
                read: $i % 2 === 0,
            );
        }
    }

    /**
     * @param array{notification_type: string, title: string, message: string, action_url: string} $data
     */
    private function createNotification(User $user, array $data, Carbon $createdAt, bool $read): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'database-seed',
            'data' => $data,
            'read_at' => $read ? $createdAt->copy()->addMinutes(5) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
