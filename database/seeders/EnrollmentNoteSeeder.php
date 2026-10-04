<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 開発用 受講生メモシーダー。
 */
final class EnrollmentNoteSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@certify-lms.test')->first();

        if ($admin === null) {
            $this->command?->warn('EnrollmentNoteSeeder: 固定 admin アカウントが存在しません。メモ投入をスキップします。');

            return;
        }

        $this->seedFixedStudentNotes($admin);
        $this->seedDemoStudentNotes($admin);
    }

    private function seedFixedStudentNotes(User $admin): void
    {
        $enrollments = Enrollment::query()
            ->with(['certification.coaches', 'user'])
            ->whereHas('user', fn ($q) => $q->where('email', 'student@certify-lms.test'))
            ->orderBy('created_at')
            ->get();

        foreach ($enrollments as $index => $enrollment) {
            foreach ($enrollment->certification->coaches as $coachIndex => $coach) {
                $this->firstOrCreateNote(
                    enrollment: $enrollment,
                    author: $coach,
                    body: $coachIndex === 0
                        ? '直近のチャット返信が少し遅れ気味。次回面談で学習時間の確保状況を確認する。'
                        : 'Q&A では用語定義の整理でつまずきが見える。例題ベースで理解確認したい。',
                    createdAt: now()->subDays(8 - $index)->addHours($coachIndex),
                );
            }

            if ($index === 0) {
                $this->firstOrCreateNote(
                    enrollment: $enrollment,
                    author: $admin,
                    body: '運営観察メモ。担当変更時に直近のフォロー方針を確認できるよう、面談外の状況も残す。',
                    createdAt: now()->subDays(3),
                );
            }
        }
    }

    private function seedDemoStudentNotes(User $admin): void
    {
        $enrollments = Enrollment::query()
            ->with(['certification.coaches', 'user'])
            ->whereHas('user', function ($q) {
                $q->where('role', UserRole::Student->value)
                    ->whereNotIn('email', [
                        'student@certify-lms.test',
                        'student-noquota@certify-lms.test',
                    ]);
            })
            ->orderBy('created_at')
            ->limit(8)
            ->get();

        foreach ($enrollments as $index => $enrollment) {
            $coach = $enrollment->certification->coaches->first();

            if ($coach !== null) {
                $this->firstOrCreateNote(
                    enrollment: $enrollment,
                    author: $coach,
                    body: match ($index % 3) {
                        0 => '演習の正答率は安定しているが、復習ログが短い。理解の言語化を促す。',
                        1 => '模試前に苦手カテゴリを再確認したい。次回面談で優先順位を整理する。',
                        default => '学習ペースが落ちているため、チャットで今週の確保時間を確認する。',
                    },
                    createdAt: now()->subDays(12 - $index),
                );
            }

            if ($index % 4 === 0) {
                $this->firstOrCreateNote(
                    enrollment: $enrollment,
                    author: $admin,
                    body: '管理者メモ。担当コーチ以外からも履歴確認できることを確認するためのサンプル。',
                    createdAt: now()->subDays(6 - min($index, 5)),
                );
            }
        }
    }

    private function firstOrCreateNote(
        Enrollment $enrollment,
        User $author,
        string $body,
        Carbon $createdAt,
    ): void {
        EnrollmentNote::query()->firstOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'user_id' => $author->id,
                'body' => $body,
            ],
            [
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ],
        );
    }
}
