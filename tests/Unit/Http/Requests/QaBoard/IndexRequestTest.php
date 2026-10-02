<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板一覧 IndexRequest のバリデーション検証。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_empty_filters(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $response->assertSuccessful();
    }

    public function test_validation_passes_with_all_filters_set(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->get(route('qa-board.index', [
            'keyword' => 'test',
            'status' => QaThreadStatus::Resolved->value,
            'certification_id' => $certification->id,
        ]));

        $response->assertSuccessful();
    }

    #[DataProvider('invalidFilterPayloads')]
    public function test_validation_fails(array $params, string $expectedErrorField): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->getJson(route('qa-board.index', $params));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_coach_can_access_index_request(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->get(route('qa-board.index'));

        $response->assertSuccessful();
    }

    public function test_admin_cannot_access_public_qa_board_index(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('qa-board.index'));

        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidFilterPayloads(): array
    {
        return [
            'keyword 101 文字で 422' => [['keyword' => str_repeat('a', 101)], 'keyword'],
            'status 不正値で 422' => [['status' => 'unknown'], 'status'],
            'certification_id 不正 ulid で 422' => [['certification_id' => 'not-ulid'], 'certification_id'],
            'certification_id 存在しない ulid で 422' => [
                ['certification_id' => (string) Str::ulid()],
                'certification_id',
            ],
        ];
    }
}
