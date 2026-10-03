<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 開発用 個人学習目標シーダー。
 *
 * EnrollmentSeeder で生成済みの受講登録に対して、受講生本人 / coach / admin の閲覧確認と
 * 達成マーク UI の実機確認に使う個人目標を投入する。
 */
final class EnrollmentGoalSeeder extends Seeder
{
    public function run(): void
    {
        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($fixedStudent !== null) {
            $this->seedFixedStudentGoals($fixedStudent);
        }

        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotIn('email', ['student@certify-lms.test', 'student-noquota@certify-lms.test'])
            ->orderBy('created_at')
            ->limit(8)
            ->get();

        if ($fixedStudent === null && $demoStudents->isEmpty()) {
            $this->command?->warn('EnrollmentGoalSeeder: 対象受講生が存在しません。先に UserSeeder / EnrollmentSeeder を実行してください。');

            return;
        }

        foreach ($demoStudents as $index => $student) {
            $this->seedDemoStudentGoals($student, $index);
        }
    }

    private function seedFixedStudentGoals(User $student): void
    {
        $enrollments = $student->enrollments()
            ->where('status', EnrollmentStatus::Learning->value)
            ->orderBy('created_at')
            ->limit(4)
            ->get();

        if ($enrollments->isEmpty()) {
            return;
        }

        foreach ($enrollments as $index => $enrollment) {
            $this->seedGoals($enrollment, $this->fixedStudentGoalSet($index));
        }
    }

    /**
     * @return array<int, array{title: string, description: ?string, target_date: string, achieved_at: ?Carbon}>
     */
    private function fixedStudentGoalSet(int $index): array
    {
        return match ($index) {
            0 => [
                [
                    'title' => '基礎講座を一通り終える',
                    'description' => '第 1 章から最終章までを視聴し、章末問題を復習する。',
                    'target_date' => now()->addDays(14)->toDateString(),
                    'achieved_at' => null,
                ],
                [
                    'title' => '過去問 1 年分を解き直す',
                    'description' => '間違えた問題をノートにまとめ、次回面談で相談する。',
                    'target_date' => now()->addDays(7)->toDateString(),
                    'achieved_at' => now()->subDays(2),
                ],
            ],
            1 => [
                [
                    'title' => '重要用語を 30 個確認する',
                    'description' => '暗記カードを作り、説明できない用語を洗い出す。',
                    'target_date' => now()->addDays(10)->toDateString(),
                    'achieved_at' => now()->subDays(1),
                ],
                [
                    'title' => '章末問題を 2 章分解く',
                    'description' => '正答率 80% を目安に、間違えた設問を復習する。',
                    'target_date' => now()->addDays(18)->toDateString(),
                    'achieved_at' => null,
                ],
            ],
            2 => [
                [
                    'title' => '模試前の弱点を整理する',
                    'description' => '直近の演習履歴から苦手カテゴリを 3 つ選ぶ。',
                    'target_date' => now()->addDays(12)->toDateString(),
                    'achieved_at' => null,
                ],
                [
                    'title' => '学習時間を週 6 時間確保する',
                    'description' => '平日 30 分、休日 2 時間のペースで進める。',
                    'target_date' => now()->addDays(21)->toDateString(),
                    'achieved_at' => null,
                ],
            ],
            default => [
                [
                    'title' => '試験範囲の全体像を把握する',
                    'description' => '目次と出題範囲を確認し、学習順序を決める。',
                    'target_date' => now()->addDays(8)->toDateString(),
                    'achieved_at' => now()->subDays(3),
                ],
                [
                    'title' => '次回面談で相談する内容をまとめる',
                    'description' => '学習計画と不安な範囲をメモに整理する。',
                    'target_date' => now()->addDays(16)->toDateString(),
                    'achieved_at' => null,
                ],
            ],
        };
    }

    private function seedDemoStudentGoals(User $student, int $index): void
    {
        $enrollment = $student->enrollments()
            ->orderBy('created_at')
            ->first();

        if ($enrollment === null) {
            return;
        }

        $this->seedGoals($enrollment, [
            [
                'title' => '今週の学習範囲を決める',
                'description' => $index % 2 === 0 ? '教材一覧を確認して、優先して進める章を 2 つ選ぶ。' : null,
                'target_date' => now()->addDays(5 + $index)->toDateString(),
                'achieved_at' => null,
            ],
            [
                'title' => '苦手分野を 1 つ復習する',
                'description' => '直近の演習結果から復習テーマを選ぶ。',
                'target_date' => now()->addDays(10 + $index)->toDateString(),
                'achieved_at' => $index % 3 === 0 ? now()->subDays(1) : null,
            ],
        ]);
    }

    /**
     * @param array<int, array{title: string, description: ?string, target_date: string, achieved_at: ?Carbon}> $goals
     */
    private function seedGoals(Enrollment $enrollment, array $goals): void
    {
        foreach ($goals as $goal) {
            EnrollmentGoal::firstOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'title' => $goal['title'],
                ],
                [
                    'user_id' => $enrollment->user_id,
                    'target_date' => $goal['target_date'],
                    'description' => $goal['description'],
                    'achieved_at' => $goal['achieved_at'],
                ],
            );
        }
    }
}
