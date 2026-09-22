<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Part;

use App\Models\Certification;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reorder_updates_order_columns(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $a = Part::factory()->forCertification($cert)->state(['order' => 1])->create();
        $b = Part::factory()->forCertification($cert)->state(['order' => 2])->create();
        $c = Part::factory()->forCertification($cert)->state(['order' => 3])->create();

        $this->actingAs($admin)
            ->patch(route('admin.certifications.parts.reorder', $cert), [
                'ids' => [$c->id, $a->id, $b->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, $c->fresh()->order);
        $this->assertSame(2, $a->fresh()->order);
        $this->assertSame(3, $b->fresh()->order);
    }

    public function test_reorder_rejects_cross_certification_ids(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        $a = Part::factory()->forCertification($cert)->state(['order' => 1])->create();
        $foreign = Part::factory()->forCertification($otherCert)->state(['order' => 1])->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.certifications.parts.reorder', $cert), [
                'ids' => [$a->id, $foreign->id],
            ])
            ->assertStatus(422);
    }

    public function test_coach_can_reorder_updates_order_columns(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $cert->coaches()->attach($coach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $a = Part::factory()->forCertification($cert)->state(['order' => 1])->create();
        $b = Part::factory()->forCertification($cert)->state(['order' => 2])->create();
        $c = Part::factory()->forCertification($cert)->state(['order' => 3])->create();

        $this->actingAs($coach)
            ->patch(route('admin.certifications.parts.reorder', $cert), [
                'ids' => [$c->id, $a->id, $b->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, $c->fresh()->order);
        $this->assertSame(2, $a->fresh()->order);
        $this->assertSame(3, $b->fresh()->order);
    }
}
