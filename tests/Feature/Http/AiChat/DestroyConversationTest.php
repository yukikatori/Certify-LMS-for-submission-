<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `DELETE /ai-chat/conversations/{conversation}` の会話削除を検証する。
 */
class DestroyConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-chat.enabled' => true]);
    }

    public function test_owner_can_delete_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = $this->createConversation($student);

        $response = $this->actingAs($student)->delete(route('ai-chat.conversations.destroy', $conversation));

        $response->assertRedirect(route('ai-chat.index'));
        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $conversation->id]);
    }

    public function test_non_owner_cannot_delete_conversation(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $conversation = $this->createConversation($owner);

        $this->actingAs($other)
            ->delete(route('ai-chat.conversations.destroy', $conversation))
            ->assertForbidden();

        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $conversation->id]);
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
