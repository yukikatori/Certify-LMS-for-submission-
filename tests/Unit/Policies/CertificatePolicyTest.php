<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Policies\CertificatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificatePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_any_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $certificate = Certificate::factory()->create();
        $policy = new CertificatePolicy;

        $this->assertTrue($policy->download($admin, $certificate));
    }

    public function test_student_can_download_only_own_certificate(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $ownCertificate = Certificate::factory()->forEnrollment(
            Enrollment::factory()->for($student)->passed()->create()
        )->create();
        $othersCertificate = Certificate::factory()->forEnrollment(
            Enrollment::factory()->for($other)->passed()->create()
        )->create();
        $policy = new CertificatePolicy;

        $this->assertTrue($policy->download($student, $ownCertificate));
        $this->assertFalse($policy->download($student, $othersCertificate));
    }

    public function test_graduated_student_can_download_own_certificate(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $certificate = Certificate::factory()->forEnrollment(
            Enrollment::factory()->for($student)->passed()->create()
        )->create();
        $policy = new CertificatePolicy;

        $this->assertTrue($policy->download($student, $certificate));
    }

    public function test_coach_can_download_only_assigned_certification_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $assignedCertification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $assignedCertification->coaches()->attach($coach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $assignedCertificate = Certificate::factory()->forEnrollment(
            Enrollment::factory()->for($assignedCertification)->passed()->create()
        )->create();
        $otherCertificate = Certificate::factory()->forEnrollment(
            Enrollment::factory()->for($otherCertification)->passed()->create()
        )->create();
        $policy = new CertificatePolicy;

        $this->assertTrue($policy->download($coach, $assignedCertificate));
        $this->assertFalse($policy->download($coach, $otherCertificate));
    }
}
