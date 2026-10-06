<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /ai-chat` の挙動を検証する。
 *
 * 会話が存在すれば最新会話へ redirect、0 件なら empty-state を表示する。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-chat.enabled' => true]);
    }

    public function test_student_with_conversations_redirects_to_latest_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $older = $this->createConversation($student, now()->subDay(), '古い相談');
        $newer = $this->createConversation($student, now()->subMinutes(5), '新しい相談');

        $response = $this->actingAs($student)->get(route('ai-chat.index'));

        $response->assertRedirect(route('ai-chat.conversations.show', $newer));
        $this->assertNotSame($older->id, $newer->id);
    }

    public function test_other_students_conversations_do_not_become_redirect_target(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $this->createConversation($other, now(), '他受講生の相談');

        $response = $this->actingAs($student)->get(route('ai-chat.index'));

        $response->assertOk();
        $response->assertViewIs('ai-chat.empty-state');
    }

    public function test_empty_state_when_no_conversations(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->get(route('ai-chat.index'));

        $response->assertOk();
        $response->assertViewIs('ai-chat.empty-state');
    }

    public function test_coach_cannot_open_student_ai_chat_index(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get(route('ai-chat.index'))->assertForbidden();
    }

    public function test_feature_disabled_returns_not_found(): void
    {
        config(['ai-chat.enabled' => false]);
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get(route('ai-chat.index'))->assertNotFound();
    }

    private function createConversation(User $student, mixed $lastMessageAt, string $title): AiChatConversation
    {
        return AiChatConversation::create([
            'user_id' => $student->id,
            'title' => $title,
            'auto_title_enabled' => true,
            'last_message_at' => $lastMessageAt,
        ]);
    }
}
