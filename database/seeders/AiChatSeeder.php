<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 開発用 AI 相談データ。
 *
 * 固定受講生(student@certify-lms.test)に、履歴再開・Section 文脈・AI エラー表示を
 * 実機確認できる会話を投入する。
 */
final class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();

        if ($student === null) {
            $this->command?->warn('AiChatSeeder: 固定受講生 student@certify-lms.test が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $section = Section::query()
            ->with('chapter.part')
            ->where('title', '1.1 2 進数の表現')
            ->first();

        $sectionCertificationId = $section?->chapter?->part?->certification_id;

        $learningEnrollment = $student->enrollments()
            ->with('certification')
            ->where('status', EnrollmentStatus::Learning->value)
            ->when($sectionCertificationId !== null, fn ($query) => $query->where('certification_id', $sectionCertificationId))
            ->orderBy('created_at')
            ->first();

        if ($learningEnrollment === null) {
            $this->command?->warn('AiChatSeeder: 固定受講生の学習中 Enrollment が存在しません。先に EnrollmentSeeder を実行してください。');

            return;
        }

        $student->aiChatConversations()->delete();

        $this->seedSectionConversation($student, $learningEnrollment, $section);
        $this->seedGeneralConversation($student, $learningEnrollment);
        $this->seedErrorConversation($student, $learningEnrollment);
    }

    private function seedSectionConversation(User $student, Enrollment $enrollment, ?Section $section): void
    {
        if ($section === null) {
            return;
        }

        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
            'title' => '2進数の補数表現の確認',
            'auto_title_enabled' => false,
            'last_message_at' => now()->subDays(2)->addMinutes(8),
        ]);

        $conversation->messages()->createMany([
            [
                'role' => AiChatMessageRole::User,
                'content' => '2の補数で負の数を表すところがまだ曖昧です。なぜ反転して1を足すのでしょうか?',
                'status' => AiChatMessageStatus::Completed,
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'content' => "2の補数は、足し算だけで引き算を扱えるようにする表現です。\n\n8ビットで `5` は `00000101` です。反転して `11111010`、そこに `1` を足すと `11111011` になり、これを `-5` として扱えます。試験では「反転して1を足す」という手順だけでなく、桁あふれを捨てることで足し算が成立する点を押さえると安定します。",
                'status' => AiChatMessageStatus::Completed,
                'model' => 'gemini-3.6-flash',
                'prompt_tokens' => 680,
                'output_tokens' => 142,
                'total_tokens' => 822,
                'response_time_ms' => 1240,
            ],
        ]);
    }

    private function seedGeneralConversation(User $student, Enrollment $enrollment): void
    {
        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => null,
            'title' => '午前試験の学習計画',
            'auto_title_enabled' => true,
            'last_message_at' => now()->subDay()->addMinutes(15),
        ]);

        $conversation->messages()->createMany([
            [
                'role' => AiChatMessageRole::User,
                'content' => '基本情報の午前対策で、平日30分しか取れない場合の進め方を教えてください。',
                'status' => AiChatMessageStatus::Completed,
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'content' => "平日30分なら、毎日「インプット10分、問題演習15分、見直し5分」に分けるのがおすすめです。\n\nまずテクノロジ系を優先し、間違えた問題は用語の暗記ではなく「なぜその選択肢が違うか」まで確認しましょう。週末にまとめて苦手分野を解き直すと定着しやすくなります。",
                'status' => AiChatMessageStatus::Completed,
                'model' => 'gemini-3.6-flash',
                'prompt_tokens' => 540,
                'output_tokens' => 118,
                'total_tokens' => 658,
                'response_time_ms' => 980,
            ],
        ]);
    }

    private function seedErrorConversation(User $student, Enrollment $enrollment): void
    {
        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => null,
            'title' => 'アルゴリズムの再質問',
            'auto_title_enabled' => true,
            'last_message_at' => now()->subHours(3),
        ]);

        $conversation->messages()->createMany([
            [
                'role' => AiChatMessageRole::User,
                'content' => '二分探索の計算量がなぜ O(log n) になるのか、もう一度説明してほしいです。',
                'status' => AiChatMessageStatus::Completed,
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'content' => 'AI が応答できませんでした。しばらく時間をおいて再試行してください。',
                'status' => AiChatMessageStatus::Error,
                'error_detail' => 'Gemini request failed: HTTP 503 Service Unavailable',
                'model' => 'gemini-3.6-flash',
            ],
        ]);
    }
}
