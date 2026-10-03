<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '3 ヶ月プラン 12 回',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
        ], $overrides);
    }

    public function test_admin_can_submit_valid_payload_without_optional_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload());

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'name' => '3 ヶ月プラン 12 回',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'sort_order' => 0,
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_submit_valid_payload_with_all_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload([
            'description' => '標準的な学習期間。週 1 回ペースで面談が可能。',
            'sort_order' => 20,
        ]));

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'name' => '3 ヶ月プラン 12 回',
            'description' => '標準的な学習期間。週 1 回ペースで面談が可能。',
            'sort_order' => 20,
            'status' => 'draft',
        ]);
    }

    public function test_name_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $this->payload([
            'name' => '',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_name_must_not_exceed_100_characters(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $this->payload([
            'name' => str_repeat('a', 101),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_description_must_not_exceed_2000_characters(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $this->payload([
            'description' => str_repeat('a', 2001),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('description');
    }

    public function test_duration_days_is_required_integer_between_1_and_3650(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['' => 'required', 0 => 'min', 3651 => 'max', 'abc' => 'integer'] as $value => $case) {
            $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $this->payload([
                'duration_days' => $value,
            ]));

            $response->assertStatus(422, "duration_days {$case} should fail.");
            $response->assertJsonValidationErrors('duration_days');
        }
    }

    public function test_default_meeting_quota_is_required_integer_between_0_and_1000(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['' => 'required', -1 => 'min', 1001 => 'max', 'abc' => 'integer'] as $value => $case) {
            $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $this->payload([
                'default_meeting_quota' => $value,
            ]));

            $response->assertStatus(422, "default_meeting_quota {$case} should fail.");
            $response->assertJsonValidationErrors('default_meeting_quota');
        }
    }

    public function test_sort_order_is_nullable_integer_at_least_zero(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload(['sort_order' => null]))
            ->assertSessionDoesntHaveErrors();

        foreach ([-1, 'abc'] as $value) {
            $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $this->payload([
                'sort_order' => $value,
            ]));

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('sort_order');
        }
    }

    public function test_coach_cannot_store_plan(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->postJson(route('admin.plans.store'), $this->payload());

        $response->assertForbidden();
    }

    public function test_student_cannot_store_plan(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->postJson(route('admin.plans.store'), $this->payload());

        $response->assertForbidden();
    }
}
