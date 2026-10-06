<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AI 相談 メッセージ送信 StoreMessageRequest のバリデーション検証。
 * content の必須 / 文字数上限を valid + invalid で網羅し、
 * authorize は会話オーナーのみ true を検証する。
 */
class StoreMessageRequestTest extends TestCase
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

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $conversation = $this->createConversation($student);

        // Act
        $response = $this->actingAs($student)->postJson(route('ai-chat.conversations.messages.store', $conversation), [
            'content' => str_repeat('a', 2000),
        ]);

        // Assert
        $response->assertOk();
        $response->assertJsonPath('user_message.role', 'user');
        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => str_repeat('a', 2000),
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $conversation = $this->createConversation($student);
        $payload = array_merge(['content' => '質問です'], $overrides);

        // Act
        $response = $this->actingAs($student)->postJson(route('ai-chat.conversations.messages.store', $conversation), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_authorize_returns_false_for_non_owner(): void
    {
        // Arrange
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $conversation = $this->createConversation($owner);

        // Act
        $response = $this->actingAs($other)->postJson(route('ai-chat.conversations.messages.store', $conversation), [
            'content' => '質問です',
        ]);

        // Assert
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'content 未指定で 422' => [['content' => ''], 'content'],
            'content 2001 文字で 422' => [['content' => str_repeat('a', 2001)], 'content'],
        ];
    }

    private function createConversation(User $owner): AiChatConversation
    {
        return AiChatConversation::create([
            'user_id' => $owner->id,
            'title' => 'AI 相談',
            'auto_title_enabled' => true,
            'last_message_at' => now(),
        ]);
    }
}
