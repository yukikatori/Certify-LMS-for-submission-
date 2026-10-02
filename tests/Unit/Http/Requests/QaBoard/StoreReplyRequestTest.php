<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaBoard;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板 StoreReplyRequest のバリデーション検証。
 */
class StoreReplyRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        Notification::fake();

        $student = User::factory()->student()->create();
        $replyStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forStudent($student)->forCertification($certification)->create();

        $response = $this->actingAs($replyStudent)->post(route('qa-board.replies.store', $thread), [
            'body' => 'reply',
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $replyStudent->id,
            'body' => 'reply',
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        Notification::fake();

        $student = User::factory()->student()->create();
        $replyStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forStudent($student)->forCertification($certification)->create();

        $payload = array_merge([
            'body' => 'test',
        ], $overrides);

        $response = $this->actingAs($replyStudent)
            ->postJson(route('qa-board.replies.store', $thread), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_coach_can_create_reply(): void
    {
        Notification::fake();

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

        $response = $this->actingAs($replyCoach)->post(route('qa-board.replies.store', $thread), [
            'body' => 'reply',
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $replyCoach->id,
            'body' => 'reply',
        ]);
    }

    public function test_unassigned_coach_cannot_create_reply(): void
    {
        Notification::fake();

        $student = User::factory()->student()->create();
        $unassignedCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($unassignedCoach)->post(route('qa-board.replies.store', $thread), [
            'body' => 'reply',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $unassignedCoach->id,
            'body' => 'reply',
        ]);
    }

    public function test_admin_cannot_create_reply(): void
    {
        Notification::fake();

        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($admin)->post(route('qa-board.replies.store', $thread), [
            'body' => 'reply',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $admin->id,
            'body' => 'reply',
        ]);
    }

    public function test_student_cannot_reply_to_unpublished_certification_thread(): void
    {
        Notification::fake();

        $student = User::factory()->student()->create();
        $certification = Certification::factory()->draft()->create();
        $thread = QaThread::factory()->forStudent($student)->forCertification($certification)->create();

        $response = $this->actingAs($student)->post(route('qa-board.replies.store', $thread), [
            'body' => 'reply',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => 'reply',
        ]);
    }

    public function test_coach_cannot_reply_to_unpublished_certification_thread(): void
    {
        Notification::fake();

        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->draft()->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
        ]);

        $thread = QaThread::factory()->forStudent($student)->forCertification($certification)->create();

        $response = $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => 'reply',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
            'body' => 'reply',
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
