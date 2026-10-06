<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `POST /ai-chat/conversations` の会話作成を検証する。
 */
class StoreConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-chat.enabled' => true]);
    }

    public function test_student_can_create_general_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->post(route('ai-chat.conversations.store'), [
            'source' => 'full-screen',
        ]);

        $conversation = AiChatConversation::query()->where('user_id', $student->id)->first();
        $this->assertNotNull($conversation);
        $response->assertRedirect(route('ai-chat.conversations.show', $conversation));
        $this->assertSame('新しい相談', $conversation->title);
    }

    public function test_json_request_returns_created_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->postJson(route('ai-chat.conversations.store'), [
            'source' => 'widget',
        ]);

        $conversation = AiChatConversation::query()->where('user_id', $student->id)->first();
        $this->assertNotNull($conversation);
        $response->assertCreated();
        $response->assertJsonPath('conversation.id', $conversation->id);
        $response->assertJsonPath('conversation.title', '新しい相談');
    }

    public function test_section_conversation_reuses_existing_conversation(): void
    {
        [$student, $section] = $this->createStudentWithSectionEnrollment();
        $existing = AiChatConversation::create([
            'user_id' => $student->id,
            'section_id' => $section->id,
            'title' => '既存の Section 相談',
            'auto_title_enabled' => true,
            'last_message_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($student)->postJson(route('ai-chat.conversations.store'), [
            'source' => 'widget',
            'section_id' => $section->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('conversation.id', $existing->id);
        $this->assertSame(1, AiChatConversation::query()->where('section_id', $section->id)->count());
    }

    public function test_student_cannot_create_section_conversation_without_learning_enrollment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $section = Section::factory()->published()->create();

        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
                'section_id' => $section->id,
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Section}
     */
    private function createStudentWithSectionEnrollment(): array
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($certification)->published()->create();
        $chapter = Chapter::factory()->forPart($part)->published()->create();
        $section = Section::factory()->forChapter($chapter)->published()->create();
        Enrollment::factory()->for($student)->for($certification)->learning()->create();

        return [$student, $section];
    }
}
