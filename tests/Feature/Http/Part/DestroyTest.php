<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Part;

use App\Models\Certification;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_soft_delete_draft_part(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->draft()->create();

        $this->actingAs($admin)
            ->delete(route('admin.parts.destroy', $part))
            ->assertRedirect(route('admin.certifications.parts.index', $cert));

        $this->assertDatabaseMissing('parts', ['id' => $part->id]);
    }

    public function test_admin_cannot_delete_published_part(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->published()->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.parts.destroy', $part))
            ->assertStatus(409);

        $this->assertDatabaseHas('parts', ['id' => $part->id]);
    }

    public function test_coach_can_soft_delete_draft_part(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $cert->coaches()->attach($coach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $part = Part::factory()->forCertification($cert)->draft()->create();

        $this->actingAs($coach)
            ->delete(route('admin.parts.destroy', $part))
            ->assertRedirect(route('admin.certifications.parts.index', $cert));

        $this->assertDatabaseMissing('parts', ['id' => $part->id]);
    }

    public function test_coach_cannot_delete_published_part(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $cert->coaches()->attach($coach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $part = Part::factory()->forCertification($cert)->published()->create();

        $this->actingAs($coach)
            ->deleteJson(route('admin.parts.destroy', $part))
            ->assertStatus(409);

        $this->assertDatabaseHas('parts', ['id' => $part->id]);
    }
}
