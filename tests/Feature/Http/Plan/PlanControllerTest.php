<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => '3 ヶ月プラン',
            'description' => '標準の受講プランです。',
            'duration_days' => 90,
            'default_meeting_quota' => 8,
            'sort_order' => 10,
        ], $overrides);
    }

    public function test_admin_can_list_plans_with_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $matched = Plan::factory()->published()->create(['name' => '集中学習プラン']);
        $draft = Plan::factory()->draft()->create(['name' => '集中学習プラン 下書き']);
        $other = Plan::factory()->published()->create(['name' => '通常プラン']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index', [
            'keyword' => '集中学習',
            'status' => PlanStatus::Published->value,
        ]));

        $response->assertOk();
        $response->assertViewIs('plan.management.index');
        $response->assertViewHas('plans', fn ($plans) => $plans->contains('id', $matched->id)
            && ! $plans->contains('id', $draft->id)
            && ! $plans->contains('id', $other->id));
        $response->assertViewHas('keyword', '集中学習');
        $response->assertViewHas('status', PlanStatus::Published->value);
    }

    public function test_admin_can_show_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $student = User::factory()->student()->withPlan($plan)->create();
        User::factory()->student()->graduated()->withPlan($plan)->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertViewIs('plan.management.show');
        $response->assertViewHas('plan', fn (Plan $viewPlan) => $viewPlan->is($plan)
            && $viewPlan->relationLoaded('createdBy')
            && $viewPlan->relationLoaded('updatedBy')
            && $viewPlan->relationLoaded('users')
            && $viewPlan->users->contains('id', $student->id)
            && $viewPlan->users->count() === 1);
    }

    public function test_admin_can_create_plan_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.plans.store'),
            $this->validPayload(['sort_order' => null]),
        );

        $plan = Plan::query()->where('name', '3 ヶ月プラン')->firstOrFail();

        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '3 ヶ月プラン',
            'duration_days' => 90,
            'default_meeting_quota' => 8,
            'status' => PlanStatus::Draft->value,
            'sort_order' => 0,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_update_plan_without_changing_status(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create([
            'name' => '更新前プラン',
            'sort_order' => 7,
        ]);

        $response = $this->actingAs($admin)->patch(
            route('admin.plans.update', $plan),
            $this->validPayload([
                'name' => '更新後プラン',
                'status' => PlanStatus::Archived->value,
                'sort_order' => null,
            ]),
        );

        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '更新後プラン',
            'status' => PlanStatus::Published->value,
            'sort_order' => 7,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_delete_only_unused_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = Plan::factory()->draft()->create();
        $published = Plan::factory()->published()->create();
        $usedDraft = Plan::factory()->draft()->create();
        User::factory()->student()->withPlan($usedDraft)->create();

        $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $draft))
            ->assertRedirect(route('admin.plans.index'));
        $this->assertDatabaseMissing('plans', ['id' => $draft->id]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.plans.destroy', $published))
            ->assertStatus(409);
        $this->assertDatabaseHas('plans', ['id' => $published->id]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.plans.destroy', $usedDraft))
            ->assertStatus(409);
        $this->assertDatabaseHas('plans', ['id' => $usedDraft->id]);
    }

    public function test_admin_can_transition_plan_lifecycle(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.publish', $plan))
            ->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame(PlanStatus::Published, $plan->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.plans.archive', $plan))
            ->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame(PlanStatus::Archived, $plan->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.plans.unarchive', $plan))
            ->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame(PlanStatus::Draft, $plan->fresh()->status);
    }

    public function test_store_validation_fails(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.store'), $this->validPayload(['name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->actingAs($admin)
            ->postJson(route('admin.plans.store'), $this->validPayload(['duration_days' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('duration_days');

        $this->actingAs($admin)
            ->postJson(route('admin.plans.store'), $this->validPayload(['default_meeting_quota' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('default_meeting_quota');
    }

    public function test_update_validation_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.plans.update', $plan), $this->validPayload(['name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->actingAs($admin)
            ->patchJson(route('admin.plans.update', $plan), $this->validPayload(['duration_days' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('duration_days');

        $this->actingAs($admin)
            ->patchJson(route('admin.plans.update', $plan), $this->validPayload(['default_meeting_quota' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('default_meeting_quota');
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transition_returns_conflict(string $factoryState, string $routeName): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->{$factoryState}()->create();

        $this->actingAs($admin)
            ->postJson(route($routeName, $plan))
            ->assertStatus(409);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidTransitions(): array
    {
        return [
            'published cannot publish' => ['published', 'admin.plans.publish'],
            'archived cannot publish' => ['archived', 'admin.plans.publish'],
            'draft cannot archive' => ['draft', 'admin.plans.archive'],
            'archived cannot archive' => ['archived', 'admin.plans.archive'],
            'draft cannot unarchive' => ['draft', 'admin.plans.unarchive'],
            'published cannot unarchive' => ['published', 'admin.plans.unarchive'],
        ];
    }

    #[DataProvider('adminOnlyRoutes')]
    public function test_student_and_coach_cannot_access_plan_management(
        string $method,
        string $routeName,
        ?string $factoryState = null,
    ): void {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $plan = $factoryState === null ? null : Plan::factory()->{$factoryState}()->create();
        $payload = in_array($method, ['post', 'patch'], true) ? $this->validPayload() : [];

        $this->actingAs($student)
            ->{$method}(route($routeName, $plan), $payload)
            ->assertForbidden();

        $this->actingAs($coach)
            ->{$method}(route($routeName, $plan), $payload)
            ->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2?: ?string}>
     */
    public static function adminOnlyRoutes(): array
    {
        return [
            'index' => ['get', 'admin.plans.index'],
            'create' => ['get', 'admin.plans.create'],
            'store' => ['post', 'admin.plans.store'],
            'show' => ['get', 'admin.plans.show', 'draft'],
            'edit' => ['get', 'admin.plans.edit', 'draft'],
            'update' => ['patch', 'admin.plans.update', 'draft'],
            'destroy' => ['delete', 'admin.plans.destroy', 'draft'],
            'publish' => ['post', 'admin.plans.publish', 'draft'],
            'archive' => ['post', 'admin.plans.archive', 'published'],
            'unarchive' => ['post', 'admin.plans.unarchive', 'archived'],
        ];
    }
}
