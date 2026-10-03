<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_without_filters(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertSuccessful();
    }

    public function test_admin_can_filter_by_keyword_status_and_page(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->create(['name' => '3 ヶ月プラン 12 回']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index', [
            'keyword' => '3 ヶ月',
            'status' => PlanStatus::Published->value,
            'page' => 1,
        ]));

        $response->assertSuccessful();
        $response->assertSee('3 ヶ月プラン 12 回');
    }

    public function test_keyword_must_not_exceed_100_characters(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson(route('admin.plans.index', [
            'keyword' => str_repeat('a', 101),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('keyword');
    }

    public function test_status_must_be_valid_plan_status(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson(route('admin.plans.index', [
            'status' => 'unknown',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    public function test_page_must_be_positive_integer(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([0, 'abc'] as $page) {
            $response = $this->actingAs($admin)->getJson(route('admin.plans.index', [
                'page' => $page,
            ]));

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('page');
        }
    }

    public function test_coach_cannot_access_index(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->get(route('admin.plans.index'));

        $response->assertForbidden();
    }

    public function test_student_cannot_access_index(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('admin.plans.index'));

        $response->assertForbidden();
    }
}
