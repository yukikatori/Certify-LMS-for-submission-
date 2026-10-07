<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use App\Services\AiChat\AiChatResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('external-api')]
class AiChatResponderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.gemini.api_key' => 'gemini-test-key',
            'ai-chat.gemini.model' => 'gemini-test-model',
            'ai-chat.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'ai-chat.gemini.retry_attempts' => 0,
        ]);
    }

    public function test_respond_builds_prompt_structure_for_gemini(): void
    {
        [$conversation] = $this->conversationWithContext();

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '回答です。',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = app(AiChatResponder::class)->respond($conversation);

        $this->assertSame('回答です。', $response->text);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $systemPrompt = $payload['systemInstruction']['parts'][0]['text'] ?? '';
            $contents = $payload['contents'] ?? [];

            return str_contains($systemPrompt, 'あなたは資格学習を支援するAI相談役です。')
                && str_contains($systemPrompt, '対象資格: Laravel認定')
                && str_contains($systemPrompt, '閲覧中Section: ルーティング基礎')
                && $contents[0]['role'] === 'user'
                && $contents[0]['parts'][0]['text'] === '最初の質問'
                && $contents[1]['role'] === 'model'
                && $contents[1]['parts'][0]['text'] === '最初の回答'
                && $contents[2]['role'] === 'user'
                && $contents[2]['parts'][0]['text'] === '追加の質問'
                && $payload['generationConfig']['temperature'] === 0.4;
        });
    }

    public function test_generate_title_builds_title_prompt_and_trims_response(): void
    {
        [$conversation] = $this->conversationWithContext();

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '「ルーティングの相談」',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $title = app(AiChatResponder::class)->generateTitle($conversation);

        $this->assertSame('ルーティングの相談', $title);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $systemPrompt = $payload['systemInstruction']['parts'][0]['text'] ?? '';
            $titlePrompt = $payload['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($systemPrompt, 'タイトル作成係')
                && str_contains($titlePrompt, '対象資格: Laravel認定')
                && str_contains($titlePrompt, 'Section: ルーティング基礎')
                && str_contains($titlePrompt, '受講生: 追加の質問')
                && $payload['generationConfig']['temperature'] === 0.2;
        });
    }

    public function test_generate_title_returns_null_for_empty_response(): void
    {
        [$conversation] = $this->conversationWithContext();

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent' => Http::response([]),
        ]);

        $title = app(AiChatResponder::class)->generateTitle($conversation);

        $this->assertNull($title);
    }

    /**
     * @return array{AiChatConversation}
     */
    private function conversationWithContext(): array
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create([
            'name' => 'Laravel認定',
        ]);
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $section = Section::factory()->published()->create([
            'title' => 'ルーティング基礎',
        ]);

        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
            'title' => 'AI 相談',
            'auto_title_enabled' => true,
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'role' => AiChatMessageRole::User,
            'content' => '最初の質問',
            'status' => AiChatMessageStatus::Completed,
        ]);

        $conversation->messages()->create([
            'role' => AiChatMessageRole::Assistant,
            'content' => '最初の回答',
            'status' => AiChatMessageStatus::Completed,
        ]);

        $conversation->messages()->create([
            'role' => AiChatMessageRole::User,
            'content' => '追加の質問',
            'status' => AiChatMessageStatus::Completed,
        ]);

        return [$conversation];
    }
}
