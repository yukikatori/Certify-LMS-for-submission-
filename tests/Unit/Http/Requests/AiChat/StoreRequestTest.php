<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\AiChat;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AI 相談 会話作成 StoreRequest のバリデーション検証。
 * nullable な source / message / section_id の組合せを valid + invalid で網羅し、
 * authorize は受講中 student のルート通過 / non-student 不通過を検証する。
 */
class StoreRequestTest extends TestCase
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

        // Act
        $response = $this->actingAs($student)->post(route('ai-chat.conversations.store'), [
            'source' => 'widget',
            'message' => null,
            'section_id' => null,
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('ai_chat_conversations', ['user_id' => $student->id]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $payload = array_merge([
            'source' => 'widget',
            'message' => '質問です',
        ], $overrides);

        // Act
        $response = $this->actingAs($student)->postJson(route('ai-chat.conversations.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_authorize_returns_false_for_non_student(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)->postJson(route('ai-chat.conversations.store'), [
            'source' => 'widget',
            'message' => '質問です',
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
            'source 不正値で 422' => [['source' => 'sidebar'], 'source'],
            'message 2001 文字で 422' => [['message' => str_repeat('a', 2001)], 'message'],
            'section_id ulid 不正で 422' => [['section_id' => 'not-ulid'], 'section_id'],
            'section_id 存在しない ulid で 422' => [['section_id' => (string) Str::ulid()], 'section_id'],
        ];
    }
}
