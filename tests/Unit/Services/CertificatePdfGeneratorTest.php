<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\CertificatePdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificatePdfGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_certificate_pdf_on_private_disk(): void
    {
        Storage::fake('private');

        $enrollment = Enrollment::factory()->passed()->create();
        $certificate = Certificate::factory()->forEnrollment($enrollment)->create();

        app(CertificatePdfGenerator::class)->generate($certificate);

        Storage::disk('private')->assertExists($certificate->pdf_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('private')->get($certificate->pdf_path));
    }
}
