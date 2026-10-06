<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /ai-chat/conversations/{conversation}` の詳細画面と JSON 応答を検証する。
 */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-chat.enabled' => true]);
    }

    public function test_owner_can_open_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = $this->createConversation($student, '補数表現の相談');

        $response = $this->actingAs($student)->get(route('ai-chat.conversations.show', $conversation));

        $response->assertOk();
        $response->assertViewIs('ai-chat.show');
        $response->assertViewHas('conversation');
        $response->assertSee('補数表現の相談');
    }

    public function test_json_request_returns_conversation_and_messages(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = $this->createConversation($student, 'JSON 復元テスト');
        $conversation->messages()->create([
            'role' => AiChatMessageRole::User,
            'content' => '2進数について教えて',
            'status' => AiChatMessageStatus::Completed,
        ]);

        $response = $this->actingAs($student)
            ->getJson(route('ai-chat.conversations.show', $conversation));

        $response->assertOk();
        $response->assertJsonPath('conversation.id', $conversation->id);
        $response->assertJsonPath('conversation.title', 'JSON 復元テスト');
        $response->assertJsonPath('messages.0.role', 'user');
        $response->assertJsonPath('messages.0.content', '2進数について教えて');
    }

    public function test_non_owner_cannot_view_conversation(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $conversation = $this->createConversation($owner, '他人の相談');

        $this->actingAs($other)
            ->get(route('ai-chat.conversations.show', $conversation))
            ->assertForbidden();
    }

    private function createConversation(User $student, string $title): AiChatConversation
    {
        return AiChatConversation::create([
            'user_id' => $student->id,
            'title' => $title,
            'auto_title_enabled' => true,
            'last_message_at' => now(),
        ]);
    }
}
