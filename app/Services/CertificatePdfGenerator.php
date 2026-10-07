<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

/**
 * Certificate を受け取って PDF を作り、private disk に保存する Service 。
 */
final class CertificatePdfGenerator
{
    public function generate(Certificate $certificate): void
    {
        $certificate->loadMissing(['user', 'certification']);

        $html = view('certificates.pdf', [
            'certificate' => $certificate,
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'ja',
            'format' => 'A4',
            'default_font' => 'ipaexg',
        ]);

        $mpdf->WriteHTML($html);

        $stored = Storage::disk('private')->put(
            $certificate->pdf_path,
            $mpdf->Output('', 'S')
        );

        if (! $stored) {
            throw new \RuntimeException('修了証 PDF の保存に失敗しました。');
        }
    }
}