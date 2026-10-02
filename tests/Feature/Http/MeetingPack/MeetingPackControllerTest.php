<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MeetingPackControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, int|string|null>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => '5回パック',
            'description' => '追加面談用のパックです。',
            'meeting_count' => 5,
            'price' => 12000,
            'stripe_price_id' => 'price_meeting_pack_test',
            'sort_order' => 10,
        ], $overrides);
    }

    public function test_admin_can_list_meeting_packs_with_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $matched = MeetingPack::factory()->published()->create(['name' => '集中面談パック']);
        $draft = MeetingPack::factory()->draft()->create(['name' => '集中面談パック 下書き']);
        $other = MeetingPack::factory()->published()->create(['name' => '通常プラン']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', [
            'keyword' => '集中面談',
            'status' => MeetingPackStatus::Published->value,
        ]));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.index');
        $response->assertViewHas('plans', fn ($plans) => $plans->contains('id', $matched->id)
            && ! $plans->contains('id', $draft->id)
            && ! $plans->contains('id', $other->id));
        $response->assertViewHas('keyword', '集中面談');
        $response->assertViewHas('status', MeetingPackStatus::Published->value);
    }

    public function test_admin_can_show_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.show', $plan));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.show');
        $response->assertViewHas('plan', fn (MeetingPack $viewPlan) => $viewPlan->is($plan)
            && $viewPlan->relationLoaded('createdBy')
            && $viewPlan->relationLoaded('updatedBy')
            && $viewPlan->relationLoaded('payments'));
    }

    public function test_admin_can_create_meeting_pack_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.meeting-packs.store'),
            $this->validPayload(['sort_order' => null]),
        );

        $plan = MeetingPack::query()->where('name', '5回パック')->firstOrFail();

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => '5回パック',
            'meeting_count' => 5,
            'price' => 12000,
            'status' => MeetingPackStatus::Draft->value,
            'sort_order' => 0,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_update_meeting_pack_without_changing_status(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create([
            'name' => '更新前パック',
            'sort_order' => 7,
        ]);

        $response = $this->actingAs($admin)->patch(
            route('admin.meeting-packs.update', $plan),
            $this->validPayload([
                'name' => '更新後パック',
                'status' => MeetingPackStatus::Published->value,
                'sort_order' => null,
            ]),
        );

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => '更新後パック',
            'status' => MeetingPackStatus::Draft->value,
            'sort_order' => 7,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_delete_draft_and_archived_but_not_published_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = MeetingPack::factory()->draft()->create();
        $archived = MeetingPack::factory()->archived()->create();
        $published = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $draft))
            ->assertRedirect(route('admin.meeting-packs.index'));
        $this->assertDatabaseMissing('meeting_packs', ['id' => $draft->id]);

        $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $archived))
            ->assertRedirect(route('admin.meeting-packs.index'));
        $this->assertDatabaseMissing('meeting_packs', ['id' => $archived->id]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.meeting-packs.destroy', $published))
            ->assertStatus(409);
        $this->assertDatabaseHas('meeting_packs', ['id' => $published->id]);
    }

    public function test_admin_can_transition_meeting_pack_lifecycle(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.publish', $plan))
            ->assertRedirect(route('admin.meeting-packs.show', $plan));
        $this->assertSame(MeetingPackStatus::Published, $plan->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.archive', $plan))
            ->assertRedirect(route('admin.meeting-packs.show', $plan));
        $this->assertSame(MeetingPackStatus::Archived, $plan->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.unarchive', $plan))
            ->assertRedirect(route('admin.meeting-packs.show', $plan));
        $this->assertSame(MeetingPackStatus::Draft, $plan->fresh()->status);
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transition_returns_conflict(string $factoryState, string $routeName): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->{$factoryState}()->create();

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
            'published cannot publish' => ['published', 'admin.meeting-packs.publish'],
            'archived cannot publish' => ['archived', 'admin.meeting-packs.publish'],
            'draft cannot archive' => ['draft', 'admin.meeting-packs.archive'],
            'archived cannot archive' => ['archived', 'admin.meeting-packs.archive'],
            'draft cannot unarchive' => ['draft', 'admin.meeting-packs.unarchive'],
            'published cannot unarchive' => ['published', 'admin.meeting-packs.unarchive'],
        ];
    }

    #[DataProvider('adminOnlyRoutes')]
    public function test_student_and_coach_cannot_access_meeting_pack_management(string $method, string $routeName, ?string $factoryState = null): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $plan = $factoryState === null ? null : MeetingPack::factory()->{$factoryState}()->create();
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
            'index' => ['get', 'admin.meeting-packs.index'],
            'create' => ['get', 'admin.meeting-packs.create'],
            'store' => ['post', 'admin.meeting-packs.store'],
            'show' => ['get', 'admin.meeting-packs.show', 'draft'],
            'edit' => ['get', 'admin.meeting-packs.edit', 'draft'],
            'update' => ['patch', 'admin.meeting-packs.update', 'draft'],
            'destroy' => ['delete', 'admin.meeting-packs.destroy', 'draft'],
            'publish' => ['post', 'admin.meeting-packs.publish', 'draft'],
            'archive' => ['post', 'admin.meeting-packs.archive', 'published'],
            'unarchive' => ['post', 'admin.meeting-packs.unarchive', 'archived'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_store_validation_fails(array $overrides, string $expectedErrorField): void
    {
        $admin = User::factory()->admin()->create();
        $payload = array_merge($this->validPayload(), $overrides);

        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors($expectedErrorField);
    }

    #[DataProvider('invalidPayloads')]
    public function test_update_validation_fails(array $overrides, string $expectedErrorField): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create();
        $payload = array_merge($this->validPayload(), $overrides);

        $this->actingAs($admin)
            ->patchJson(route('admin.meeting-packs.update', $plan), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors($expectedErrorField);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'name required' => [['name' => ''], 'name'],
            'name max' => [['name' => str_repeat('a', 101)], 'name'],
            'description max' => [['description' => str_repeat('a', 2001)], 'description'],
            'meeting_count required' => [['meeting_count' => ''], 'meeting_count'],
            'meeting_count min' => [['meeting_count' => 0], 'meeting_count'],
            'meeting_count max' => [['meeting_count' => 101], 'meeting_count'],
            'price required' => [['price' => ''], 'price'],
            'price min' => [['price' => -1], 'price'],
            'price max' => [['price' => 1000001], 'price'],
            'stripe_price_id max' => [['stripe_price_id' => str_repeat('a', 256)], 'stripe_price_id'],
            'sort_order min' => [['sort_order' => -1], 'sort_order'],
        ];
    }
}
