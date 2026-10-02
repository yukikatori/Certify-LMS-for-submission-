<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板 StoreRequest のバリデーション検証。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'test',
            'body' => 'test',
        ]);

        $thread = QaThread::query()->first();

        $this->assertNotNull($thread);
        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => 'test',
            'body' => 'test',
            'status' => QaThreadStatus::Unresolved->value,
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $payload = array_merge([
            'certification_id' => $certification->id,
            'title' => 'test',
            'body' => 'test',
        ], $overrides);

        $response = $this->actingAs($student)->postJson(route('qa-board.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_validation_fails_with_unpublished_certification(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->draft()->create();

        $response = $this->actingAs($student)->postJson(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'test',
            'body' => 'test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('certification_id');
    }

    public function test_coach_cannot_create_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($coach)->postJson(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'test',
            'body' => 'test',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_cannot_create_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->postJson(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'test',
            'body' => 'test',
        ]);

        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'certification_id 未指定で 422' => [['certification_id' => ''], 'certification_id'],
            'certification_id 不正 ulid で 422' => [['certification_id' => 'not-ulid'], 'certification_id'],
            'certification_id 存在しない ulid で 422' => [['certification_id' => (string) Str::ulid()], 'certification_id'],
            'title 未指定で 422' => [['title' => ''], 'title'],
            'title 201 文字で 422' => [['title' => str_repeat('a', 201)], 'title'],
            'body 未指定で 422' => [['body' => ''], 'body'],
            'body 5001 文字で 422' => [['body' => str_repeat('a', 5001)], 'body'],
        ];
    }
}
