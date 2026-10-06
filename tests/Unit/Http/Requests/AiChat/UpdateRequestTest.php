<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AI 相談 会話タイトル更新 UpdateRequest のバリデーション検証。
 * title の必須 / 文字数上限を valid + invalid で網羅し、
 * authorize は会話オーナーのみ true を検証する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-chat.enabled' => true]);
    }

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $conversation = $this->createConversation($student);

        // Act
        $response = $this->actingAs($student)->patch(route('ai-chat.conversations.update', $conversation), [
            'title' => str_repeat('a', 100),
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => str_repeat('a', 100),
            'auto_title_enabled' => false,
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $conversation = $this->createConversation($student);
        $payload = array_merge(['title' => 'タイトル'], $overrides);

        // Act
        $response = $this->actingAs($student)->patchJson(route('ai-chat.conversations.update', $conversation), $payload);

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
        $response = $this->actingAs($other)->patchJson(route('ai-chat.conversations.update', $conversation), [
            'title' => '上書き',
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
            'title 未指定で 422' => [['title' => ''], 'title'],
            'title 101 文字で 422' => [['title' => str_repeat('a', 101)], 'title'],
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
