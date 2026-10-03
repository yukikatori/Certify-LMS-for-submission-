<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\NotificationRecipientService;
use Tests\TestCase;

class NotificationRecipientServiceTest extends TestCase
{
    public function test_in_progress_student_and_coach_can_receive_notifications(): void
    {
        $service = new NotificationRecipientService;
        $student = User::factory()->student()->inProgress()->make();
        $coach = User::factory()->coach()->inProgress()->make();

        $this->assertTrue($service->canReceive($student));
        $this->assertTrue($service->canReceive($coach));
    }

    public function test_admin_cannot_receive_notifications(): void
    {
        $service = new NotificationRecipientService;
        $admin = User::factory()->admin()->inProgress()->make();

        $this->assertFalse($service->canReceive($admin));
    }

    public function test_non_in_progress_user_cannot_receive_notifications(): void
    {
        $service = new NotificationRecipientService;

        $this->assertFalse($service->canReceive(User::factory()->student()->graduated()->make()));
        $this->assertFalse($service->canReceive(User::factory()->student()->withdrawn()->make()));
        $this->assertFalse($service->canReceive(User::factory()->student()->invited()->make()));
    }

    public function test_soft_deleted_user_cannot_receive_notifications(): void
    {
        $service = new NotificationRecipientService;
        $user = User::factory()->student()->inProgress()->make([
            'deleted_at' => now(),
        ]);

        $this->assertFalse($service->canReceive($user));
    }
}
