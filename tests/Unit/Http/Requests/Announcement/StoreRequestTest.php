<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Announcement;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_all_students_target(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'メンテナンスのお知らせ',
            'body' => '本文です。',
            'target_type' => 'all',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('announcements', [
            'title' => 'メンテナンスのお知らせ',
            'target_type' => 'all',
        ]);
    }

    public function test_validation_passes_with_certification_target(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '資格別のお知らせ',
            'body' => '本文です。',
            'target_type' => 'certification',
            'target_certification_id' => $certification->id,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('announcements', [
            'title' => '資格別のお知らせ',
            'target_type' => 'certification',
            'target_certification_id' => $certification->id,
        ]);
    }

    public function test_validation_passes_with_user_target(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '個別のお知らせ',
            'body' => '本文です。',
            'target_type' => 'user',
            'target_user_id' => $student->id,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('announcements', [
            'title' => '個別のお知らせ',
            'target_type' => 'user',
            'target_user_id' => $student->id,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->create();

        $payload = array_merge([
            'title' => 'お知らせ',
            'body' => '本文です。',
            'target_type' => 'all',
            'target_certification_id' => null,
            'target_user_id' => null,
        ], array_map(
            fn ($value) => match ($value) {
                '__certification_id__' => $certification->id,
                '__student_id__' => $student->id,
                default => $value,
            },
            $overrides,
        ));

        $response = $this->actingAs($admin)->postJson(route('admin.announcements.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_non_admin_cannot_store_announcement(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->postJson(route('admin.announcements.store'), [
            'title' => 'お知らせ',
            'body' => '本文です。',
            'target_type' => 'all',
        ]);

        $response->assertForbidden();
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
            'body 5001 文字で 422' => [['body' => str_repeat('b', 5001)], 'body'],
            'target_type 不正値で 422' => [['target_type' => 'coach'], 'target_type'],
            '資格指定で target_certification_id 未指定は 422' => [['target_type' => 'certification'], 'target_certification_id'],
            '資格指定以外で target_certification_id 指定は 422' => [['target_certification_id' => '__certification_id__'], 'target_certification_id'],
            'target_certification_id ulid 不正で 422' => [['target_type' => 'certification', 'target_certification_id' => 'not-ulid'], 'target_certification_id'],
            'target_certification_id 存在しない ulid で 422' => [['target_type' => 'certification', 'target_certification_id' => (string) Str::ulid()], 'target_certification_id'],
            'ユーザー指定で target_user_id 未指定は 422' => [['target_type' => 'user'], 'target_user_id'],
            'ユーザー指定以外で target_user_id 指定は 422' => [['target_user_id' => '__student_id__'], 'target_user_id'],
            'target_user_id ulid 不正で 422' => [['target_type' => 'user', 'target_user_id' => 'not-ulid'], 'target_user_id'],
            'target_user_id 存在しない ulid で 422' => [['target_type' => 'user', 'target_user_id' => (string) Str::ulid()], 'target_user_id'],
        ];
    }
}
