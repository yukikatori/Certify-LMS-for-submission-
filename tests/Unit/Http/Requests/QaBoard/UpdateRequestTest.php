<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板 UpdateRequest のバリデーション検証。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forStudent($student)->forCertification($certification)->create();

        $response = $this->actingAs($student)->patch(route('qa-board.update', $thread), [
            'title' => 'modified',
            'body' => 'modified',
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => 'modified',
            'body' => 'modified',
            'status' => QaThreadStatus::Unresolved->value,
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forStudent($student)->forCertification($certification)->create();

        $payload = array_merge([
            'title' => 'test',
            'body' => 'test contents',
        ], $overrides);

        $response = $this->actingAs($student)->patchJson(route('qa-board.update', $thread), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_returns_false_for_other_author(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forStudent($student)->forCertification($certification)->create();

        $response = $this->actingAs($otherStudent)->patch(route('qa-board.update', $thread), [
            'title' => 'modified',
            'body' => 'modified',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => $thread->title,
            'body' => $thread->body,
        ]);
    }

    public function test_returns_false_for_unpublished_certification(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->draft()->create();
        $thread = QaThread::factory()->forStudent($student)->forCertification($certification)->create();

        $response = $this->actingAs($student)->patch(route('qa-board.update', $thread), [
            'title' => 'modified',
            'body' => 'modified',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => $thread->title,
            'body' => $thread->body,
        ]);
    }

    public function test_does_not_update_certification_id(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forStudent($student)->forCertification($certification)->create();

        $response = $this->actingAs($student)->patch(route('qa-board.update', $thread), [
            'certification_id' => $otherCertification->id,
            'title' => 'modified',
            'body' => 'modified',
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'certification_id' => $certification->id,
            'title' => 'modified',
            'body' => 'modified',
        ]);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'title 未指定で 422' => [['title' => ''], 'title'],
            'title 201 文字で 422' => [['title' => str_repeat('a', 201)], 'title'],
            'body 未指定で 422' => [['body' => ''], 'body'],
            'body 5001 文字で 422' => [['body' => str_repeat('a', 5001)], 'body'],
            'title 全角スペースで 422' => [['title' => '全角　スペース'], 'title'],
            'body 全角スペースで 422' => [['body' => '全角　スペース'], 'body'],
        ];
    }
}
