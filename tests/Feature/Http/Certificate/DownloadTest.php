<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_download_own_certificate_pdf(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create();
        $certificate = $this->createCertificateWithPdf($student);

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload('certificate-'.$certificate->id.'.pdf');
    }

    public function test_graduated_student_can_download_own_certificate_pdf(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->graduated()->create();
        $certificate = $this->createCertificateWithPdf($student);

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload('certificate-'.$certificate->id.'.pdf');
    }

    public function test_student_cannot_download_other_students_certificate_pdf(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create();
        $certificate = $this->createCertificateWithPdf(User::factory()->student()->create());

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_coach_can_download_assigned_certification_certificate_pdf(): void
    {
        Storage::fake('private');

        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $certificate = $this->createCertificateWithPdf(
            User::factory()->student()->create(),
            $certification
        );

        $this->actingAs($coach)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload('certificate-'.$certificate->id.'.pdf');
    }

    public function test_coach_cannot_download_unassigned_certification_certificate_pdf(): void
    {
        Storage::fake('private');

        $coach = User::factory()->coach()->create();
        $certificate = $this->createCertificateWithPdf();

        $this->actingAs($coach)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_admin_can_download_any_certificate_pdf(): void
    {
        Storage::fake('private');

        $admin = User::factory()->admin()->create();
        $certificate = $this->createCertificateWithPdf();

        $this->actingAs($admin)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload('certificate-'.$certificate->id.'.pdf');
    }

    public function test_returns_404_when_pdf_file_is_missing(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create();
        $certificate = $this->createCertificateWithPdf($student);
        Storage::disk('private')->delete($certificate->pdf_path);

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertNotFound();
    }

    private function createCertificateWithPdf(?User $student = null, ?Certification $certification = null): Certificate
    {
        $student ??= User::factory()->student()->create();
        $certification ??= Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();
        $certificate = Certificate::factory()->forEnrollment($enrollment)->create();

        Storage::disk('private')->put($certificate->pdf_path, '%PDF-1.4 test certificate');

        return $certificate;
    }
}
