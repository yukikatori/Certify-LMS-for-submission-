<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `PATCH /ai-chat/conversations/{conversation}` のタイトル更新を検証する。
 */
class UpdateConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-chat.enabled' => true]);
    }

    public function test_owner_can_update_title_and_auto_title_is_disabled(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = $this->createConversation($student);

        $response = $this->actingAs($student)->patch(route('ai-chat.conversations.update', $conversation), [
            'title' => '手動で整理したタイトル',
        ]);

        $response->assertRedirect(route('ai-chat.conversations.show', $conversation));
        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '手動で整理したタイトル',
            'auto_title_enabled' => false,
        ]);
    }

    public function test_non_owner_cannot_update_title(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $conversation = $this->createConversation($owner);

        $this->actingAs($other)
            ->patchJson(route('ai-chat.conversations.update', $conversation), ['title' => '上書き'])
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
