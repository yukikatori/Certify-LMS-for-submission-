<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\User;
use App\Policies\MeetingPackPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MeetingPackPolicy の判定を検証する Unit テスト。
 * admin は全件 CRUD 、coach / student はアクセス不可。
 */
class MeetingPackPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_only_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $policy = new MeetingPackPolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertFalse($policy->viewAny($coach));
        $this->assertFalse($policy->viewAny($student));
    }

    public function test_view_only_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $plan = MeetingPack::factory()->create();

        $policy = new MeetingPackPolicy;

        $this->assertTrue($policy->view($admin, $plan));
        $this->assertFalse($policy->view($coach, $plan));
        $this->assertFalse($policy->view($student, $plan));
    }

    public function test_create_only_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $policy = new MeetingPackPolicy;

        $this->assertTrue($policy->create($admin));
        $this->assertFalse($policy->create($coach));
        $this->assertFalse($policy->create($student));
    }

    public function test_update_only_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $plan = MeetingPack::factory()->create();

        $policy = new MeetingPackPolicy;

        $this->assertTrue($policy->update($admin, $plan));
        $this->assertFalse($policy->update($coach, $plan));
        $this->assertFalse($policy->update($student, $plan));
    }

    public function test_delete_only_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $plan = MeetingPack::factory()->create();

        $policy = new MeetingPackPolicy;

        $this->assertTrue($policy->delete($admin, $plan));
        $this->assertFalse($policy->delete($coach, $plan));
        $this->assertFalse($policy->delete($student, $plan));
    }

    public function test_publish_only_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $policy = new MeetingPackPolicy;

        $this->assertTrue($policy->publish($admin, $plan));
        $this->assertFalse($policy->publish($coach, $plan));
        $this->assertFalse($policy->publish($student, $plan));
    }

    public function test_archive_only_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $plan = MeetingPack::factory()->published()->create();

        $policy = new MeetingPackPolicy;

        $this->assertTrue($policy->archive($admin, $plan));
        $this->assertFalse($policy->archive($coach, $plan));
        $this->assertFalse($policy->archive($student, $plan));
    }

    public function test_unarchive_only_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $plan = MeetingPack::factory()->archived()->create();

        $policy = new MeetingPackPolicy;

        $this->assertTrue($policy->unarchive($admin, $plan));
        $this->assertFalse($policy->unarchive($coach, $plan));
        $this->assertFalse($policy->unarchive($student, $plan));
    }

    public function test_purchase_allowed_only_for_in_progress_student_and_published_pack(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $published = MeetingPack::factory()->published()->create();
        $draft = MeetingPack::factory()->draft()->create();
        $archived = MeetingPack::factory()->archived()->create();

        $policy = new MeetingPackPolicy;

        $this->assertTrue($policy->purchase($student, $published));
        $this->assertFalse($policy->purchase($student, $draft));
        $this->assertFalse($policy->purchase($student, $archived));
    }

    public function test_purchase_denied_for_non_student_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $published = MeetingPack::factory()->published()->create();

        $policy = new MeetingPackPolicy;

        $this->assertFalse($policy->purchase($admin, $published));
        $this->assertFalse($policy->purchase($coach, $published));
    }

    public function test_purchase_denied_for_student_not_in_progress(): void
    {
        $published = MeetingPack::factory()->published()->create();
        $policy = new MeetingPackPolicy;

        foreach ([UserStatus::Invited, UserStatus::Graduated, UserStatus::Withdrawn] as $status) {
            $student = User::factory()->student()->create(['status' => $status->value]);

            $this->assertFalse($policy->purchase($student, $published));
        }
    }
}
