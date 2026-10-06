<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `POST /ai-chat/conversations/{conversation}/messages` の送信処理を検証する。
 *
 * Gemini API の通信成功/失敗モックは T-A-04 側で扱うため、ここでは API キー未設定時の
 * アプリ内フォールバックと認可境界を確認する。
 */
class StoreMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => null,
        ]);
    }

    public function test_owner_can_send_message_and_question_remains_when_api_key_missing(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = $this->createConversation($student);

        $response = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => 'この問題の考え方を教えてください。',
            ]);

        $response->assertOk();
        $response->assertJsonPath('user_message.role', 'user');
        $response->assertJsonPath('assistant_message.role', 'assistant');
        $response->assertJsonPath('assistant_message.status', 'error');
        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'この問題の考え方を教えてください。',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'status' => 'error',
        ]);
    }

    public function test_non_owner_cannot_send_message(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $conversation = $this->createConversation($owner);

        $this->actingAs($other)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => '他人の会話に送信',
            ])
            ->assertForbidden();
    }

    private function createConversation(User $student): AiChatConversation
    {
        return AiChatConversation::create([
            'user_id' => $student->id,
            'title' => 'AI 相談',
            'auto_title_enabled' => true,
            'last_message_at' => now(),
        ]);
    }
}
