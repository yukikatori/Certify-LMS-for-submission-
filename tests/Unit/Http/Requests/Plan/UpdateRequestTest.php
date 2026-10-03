<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateRequestTest extends TestCase
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
            'name' => '更新後プラン',
            'duration_days' => 120,
            'default_meeting_quota' => 16,
        ], $overrides);
    }

    public function test_admin_can_submit_valid_payload_without_optional_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create(['sort_order' => 30]);

        $response = $this->actingAs($admin)->put(route('admin.plans.update', $plan), $this->payload());

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '更新後プラン',
            'duration_days' => 120,
            'default_meeting_quota' => 16,
            'sort_order' => 30,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_submit_valid_payload_with_all_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->put(route('admin.plans.update', $plan), $this->payload([
            'description' => '更新後の説明',
            'sort_order' => 50,
        ]));

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '更新後プラン',
            'description' => '更新後の説明',
            'sort_order' => 50,
        ]);
    }

    public function test_status_is_not_updated_by_request_payload(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->put(route('admin.plans.update', $plan), $this->payload([
            'status' => 'archived',
        ]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('published', $plan->fresh()->status->value);
    }

    public function test_name_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $this->payload([
            'name' => '',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_name_must_not_exceed_100_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $this->payload([
            'name' => str_repeat('a', 101),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_description_must_not_exceed_2000_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $this->payload([
            'description' => str_repeat('a', 2001),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('description');
    }

    public function test_duration_days_is_required_integer_between_1_and_3650(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        foreach (['' => 'required', 0 => 'min', 3651 => 'max', 'abc' => 'integer'] as $value => $case) {
            $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $this->payload([
                'duration_days' => $value,
            ]));

            $response->assertStatus(422, "duration_days {$case} should fail.");
            $response->assertJsonValidationErrors('duration_days');
        }
    }

    public function test_default_meeting_quota_is_required_integer_between_0_and_1000(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        foreach (['' => 'required', -1 => 'min', 1001 => 'max', 'abc' => 'integer'] as $value => $case) {
            $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $this->payload([
                'default_meeting_quota' => $value,
            ]));

            $response->assertStatus(422, "default_meeting_quota {$case} should fail.");
            $response->assertJsonValidationErrors('default_meeting_quota');
        }
    }

    public function test_sort_order_is_nullable_integer_at_least_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create(['sort_order' => 10]);

        $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), $this->payload(['sort_order' => null]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(10, $plan->fresh()->sort_order);

        foreach ([-1, 'abc'] as $value) {
            $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $this->payload([
                'sort_order' => $value,
            ]));

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('sort_order');
        }
    }

    public function test_coach_cannot_update_plan(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($coach)->putJson(route('admin.plans.update', $plan), $this->payload());

        $response->assertForbidden();
    }

    public function test_student_cannot_update_plan(): void
    {
        $student = User::factory()->student()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($student)->putJson(route('admin.plans.update', $plan), $this->payload());

        $response->assertForbidden();
    }
}
