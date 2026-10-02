<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 面談パック UpdateRequest のバリデーション検証。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->patch(
            route('admin.meeting-packs.update', $plan),
            [
                'name' => 'modified',
                'description' => 'modified',
                'meeting_count' => 20,
                'price' => 20000,
                'stripe_price_id' => 'modified',
                'sort_order' => 20,
            ],
        );

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => 'modified',
            'description' => 'modified',
            'meeting_count' => 20,
            'price' => 20000,
            'stripe_price_id' => 'modified',
            'status' => MeetingPackStatus::Draft->value,
            'sort_order' => 20,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_validation_passes_when_nullable_fields_are_omitted(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->create([
            'name' => 'test',
            'description' => 'test',
            'meeting_count' => 10,
            'price' => 10000,
            'stripe_price_id' => 'test',
            'status' => MeetingPackStatus::Draft->value,
            'sort_order' => 10,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->patch(
            route('admin.meeting-packs.update', $plan),
            [
                'name' => 'modified',
                'meeting_count' => 20,
                'price' => 20000,
            ],
        );

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => 'modified',
            'description' => null,
            'meeting_count' => 20,
            'price' => 20000,
            'stripe_price_id' => null,
            'status' => MeetingPackStatus::Draft->value,
            'sort_order' => 10,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create();

        $payload = array_merge([
            'name' => 'test',
            'description' => 'test',
            'meeting_count' => 10,
            'price' => 10000,
            'stripe_price_id' => 'test',
            'sort_order' => 10,
        ], $overrides);

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.meeting-packs.update', $plan), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'name 未指定で 422' => [['name' => ''], 'name'],
            'name 101 文字で 422' => [['name' => str_repeat('a', 101)], 'name'],
            'description 2001 文字で 422' => [['description' => str_repeat('a', 2001)], 'description'],
            'meeting_count 未指定で 422' => [['meeting_count' => ''], 'meeting_count'],
            'meeting_count 0 で 422' => [['meeting_count' => 0], 'meeting_count'],
            'meeting_count 101 で 422' => [['meeting_count' => 101], 'meeting_count'],
            'price 未指定で 422' => [['price' => ''], 'price'],
            'price -1 で 422' => [['price' => -1], 'price'],
            'price 1000001 で 422' => [['price' => 1000001], 'price'],
            'stripe_price_id 256 文字で 422' => [['stripe_price_id' => str_repeat('a', 256)], 'stripe_price_id'],
            'sort_order -1 で 422' => [['sort_order' => -1], 'sort_order'],
        ];
    }

    public function test_status_cannot_be_updated_through_update_form(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->create([
            'name' => 'test',
            'description' => 'test',
            'meeting_count' => 10,
            'price' => 10000,
            'stripe_price_id' => 'test',
            'status' => MeetingPackStatus::Draft->value,
            'sort_order' => 10,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->patch(
            route('admin.meeting-packs.update', $plan),
            [
                'name' => 'modified',
                'description' => 'modified',
                'meeting_count' => 20,
                'price' => 20000,
                'status' => MeetingPackStatus::Published->value,
                'stripe_price_id' => 'modified',
                'sort_order' => 20,
            ],
        );

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => 'modified',
            'description' => 'modified',
            'meeting_count' => 20,
            'price' => 20000,
            'stripe_price_id' => 'modified',
            'status' => MeetingPackStatus::Draft->value,
            'sort_order' => 20,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_student_cannot_update_meeting_pack(): void
    {
        $student = User::factory()->student()->create();
        $plan = MeetingPack::factory()->create(['name' => 'test']);

        $response = $this->actingAs($student)->patch(
            route('admin.meeting-packs.update', $plan),
            [
                'name' => 'modified',
                'description' => 'modified',
                'meeting_count' => 20,
                'price' => 20000,
                'stripe_price_id' => 'modified',
                'sort_order' => 20,
            ],
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => 'test',
        ]);
    }

    public function test_coach_cannot_update_meeting_pack(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = MeetingPack::factory()->create(['name' => 'test']);

        $response = $this->actingAs($coach)->patch(
            route('admin.meeting-packs.update', $plan),
            [
                'name' => 'modified',
                'description' => 'modified',
                'meeting_count' => 20,
                'price' => 20000,
                'stripe_price_id' => 'modified',
                'sort_order' => 20,
            ],
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => 'test',
        ]);
    }
}
