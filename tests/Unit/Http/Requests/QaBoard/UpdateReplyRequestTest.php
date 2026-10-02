<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaBoard;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板 UpdateReplyRequestTest のバリデーション検証。
 */
class UpdateReplyRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        $student = User::factory()->student()->create();
        $replyStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forStudent($replyStudent)
            ->create();

        $response = $this->actingAs($replyStudent)
            ->patch(route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]), [
                'body' => 'modified',
            ]);

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'qa_thread_id' => $thread->id,
            'user_id' => $replyStudent->id,
            'body' => 'modified',
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        $student = User::factory()->student()->create();
        $replyStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forStudent($replyStudent)
            ->create();

        $payload = array_merge([
            'body' => 'test',
        ], $overrides);

        $response = $this->actingAs($replyStudent)
            ->patchJson(route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_returns_false_for_other_author(): void
    {
        $student = User::factory()->student()->create();
        $replyStudent = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forStudent($replyStudent)
            ->create();

        $response = $this->actingAs($otherStudent)
            ->patch(route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]), [
                'body' => 'modified',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $otherStudent->id,
            'body' => 'modified',
        ]);
    }

    public function test_returns_false_for_unpublished_certification(): void
    {
        $student = User::factory()->student()->create();
        $replyStudent = User::factory()->student()->create();
        $certification = Certification::factory()->draft()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forStudent($replyStudent)
            ->create();

        $response = $this->actingAs($replyStudent)
            ->patch(route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]), [
                'body' => 'modified',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $replyStudent->id,
            'body' => 'modified',
        ]);
    }

    public function test_coach_author_can_update_reply_for_assigned_certification(): void
    {
        $student = User::factory()->student()->create();
        $replyCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $replyCoach->id,
        ]);

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forCoach($replyCoach)
            ->create();

        $response = $this->actingAs($replyCoach)
            ->patch(route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]), [
                'body' => 'modified',
            ]);

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'qa_thread_id' => $thread->id,
            'user_id' => $replyCoach->id,
            'body' => 'modified',
        ]);
    }

    public function test_coach_author_cannot_update_reply_for_unassigned_certification(): void
    {
        $student = User::factory()->student()->create();
        $replyCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forCoach($replyCoach)
            ->create();

        $response = $this->actingAs($replyCoach)
            ->patch(route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]), [
                'body' => 'modified',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $replyCoach->id,
            'body' => 'modified',
        ]);
    }

    public function test_admin_cannot_update_reply(): void
    {
        $student = User::factory()->student()->create();
        $replyStudent = User::factory()->student()->create();
        $replyAdmin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forStudent($replyStudent)
            ->create();

        $response = $this->actingAs($replyAdmin)
            ->patch(route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]), [
                'body' => 'modified',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $replyStudent->id,
            'body' => 'modified',
        ]);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'body 未指定で 422' => [['body' => ''], 'body'],
            'body 5001 文字で 422' => [['body' => str_repeat('a', 5001)], 'body'],
            'body 全角スペースで 422' => [['body' => '全角　スペース'], 'body'],
        ];
    }
}
