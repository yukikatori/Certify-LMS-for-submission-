<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 開発用 質問掲示板シーダー。
 *
 * 公開済み資格ごとに 5 スレッドずつ作成し、回答数 / 解決状態 / 作成日時を散らす。
 * 一覧の絞り込み・並び順・ページネーション・削除可否・自分の質問導線の確認に使う。
 */
final class QaBoardSeeder extends Seeder
{
    /** @var array<int, string> */
    private const CERTIFICATION_NAMES = [
        '基本情報技術者試験',
        '応用情報技術者試験',
        'TOEIC L&R 800 点コース',
        '日商簿記 2 級',
        'PMP',
    ];

    public function run(): void
    {
        $author = User::query()->where('email', 'student@certify-lms.test')->first();
        $replyUsersByEmail = User::query()
            ->whereIn('email', [
                'coach@certify-lms.test',
                'coach2@certify-lms.test',
                'student-noquota@certify-lms.test',
            ])
            ->get()
            ->keyBy('email');

        $replyUsers = collect([
            $replyUsersByEmail->get('coach@certify-lms.test'),
            $replyUsersByEmail->get('coach2@certify-lms.test'),
            $replyUsersByEmail->get('student-noquota@certify-lms.test'),
        ])->filter()->values();

        if ($author === null || $replyUsers->count() < 3) {
            $this->command?->warn('QaBoardSeeder: 固定ユーザーが不足しています。先に UserSeeder を実行してください。');

            return;
        }

        $certifications = Certification::query()
            ->published()
            ->whereIn('name', self::CERTIFICATION_NAMES)
            ->get()
            ->keyBy('name');

        if ($certifications->count() < count(self::CERTIFICATION_NAMES)) {
            $this->command?->warn('QaBoardSeeder: 固定の公開済み資格が不足しています。先に CertificationSeeder を実行してください。');

            return;
        }

        foreach (self::CERTIFICATION_NAMES as $certificationIndex => $certificationName) {
            $certification = $certifications->get($certificationName);

            foreach ($this->threadPlans($certificationName) as $threadIndex => $plan) {
                $createdAt = now()
                    ->subDays((count(self::CERTIFICATION_NAMES) - $certificationIndex) * 4)
                    ->subHours($threadIndex * 5);

                $replyCount = $plan['reply_count'];
                $updatedAt = $replyCount > 0
                    ? $createdAt->copy()->addHours($replyCount + 1)
                    : $createdAt->copy();

                /** @var QaThread $thread */
                $thread = QaThread::query()->forceCreate([
                    'user_id' => $author->id,
                    'certification_id' => $certification->id,
                    'title' => $plan['title'],
                    'body' => $plan['body'],
                    'status' => $plan['status']->value,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);

                for ($replyIndex = 0; $replyIndex < $replyCount; $replyIndex++) {
                    $replyUser = $replyUsers[($certificationIndex + $threadIndex + $replyIndex) % $replyUsers->count()];
                    $replyAt = $createdAt->copy()->addHours($replyIndex + 1);

                    QaReply::query()->forceCreate([
                        'qa_thread_id' => $thread->id,
                        'user_id' => $replyUser->id,
                        'body' => $this->replyBody($replyUser->name, $certificationName, $replyIndex),
                        'created_at' => $replyAt,
                        'updated_at' => $replyAt,
                    ]);
                }
            }
        }
    }

    /**
     * @return array<int, array{title: string, body: string, status: QaThreadStatus, reply_count: int}>
     */
    private function threadPlans(string $certificationName): array
    {
        return [
            [
                'title' => "{$certificationName}の学習計画を立てる時の優先順位を知りたいです",
                'body' => "今週から{$certificationName}の学習を始めました。出題範囲が広いので、最初にどの分野から固めるべきか迷っています。過去問に入る前の進め方を相談したいです。",
                'status' => QaThreadStatus::Unresolved,
                'reply_count' => 0,
            ],
            [
                'title' => "{$certificationName}の頻出テーマでつまずいています",
                'body' => '教材を読み直しても、頻出テーマの考え方がまだ腹落ちしていません。例題では解けるのですが、少し条件が変わると手が止まります。',
                'status' => QaThreadStatus::Unresolved,
                'reply_count' => 2,
            ],
            [
                'title' => "{$certificationName}の過去問復習方法を見直したいです",
                'body' => '過去問を解いたあと、解説を読んで終わりにしてしまっています。次に同じタイプの問題が出たときに解けるようにする復習方法を知りたいです。',
                'status' => QaThreadStatus::Resolved,
                'reply_count' => 2,
            ],
            [
                'title' => "{$certificationName}の模試で時間が足りません",
                'body' => '模試では最後の数問を急いで解くことが多く、見直し時間も取れていません。解く順番や時間配分のコツがあれば知りたいです。',
                'status' => QaThreadStatus::Unresolved,
                'reply_count' => 3,
            ],
            [
                'title' => "{$certificationName}の苦手分野をどう潰すべきか相談したいです",
                'body' => '正答率が安定しない分野があり、問題集を繰り返しても改善している実感が薄いです。理解不足なのか演習量不足なのか切り分けたいです。',
                'status' => QaThreadStatus::Resolved,
                'reply_count' => 3,
            ],
        ];
    }

    private function replyBody(string $replyUserName, string $certificationName, int $replyIndex): string
    {
        $bodies = [
            "{$replyUserName}です。{$certificationName}では、まず間違えた理由を用語理解・手順ミス・時間不足に分けると整理しやすいです。",
            "{$replyUserName}です。似た問題を続けて解くより、翌日と3日後に解き直す形にすると定着を確認しやすくなります。",
            "{$replyUserName}です。模試では最初に全体を眺め、確実に取れる問題から進めると得点が安定しやすいです。",
        ];

        return $bodies[$replyIndex % count($bodies)];
    }
}
