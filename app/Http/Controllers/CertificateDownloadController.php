<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 修了証 PDF 出力のコントローラ。
 * 修了達成(修了証受領)のアクション直後に日本語表記の修了証 PDF を生成する。
 */
class CertificateDownloadController extends Controller
{
    public function download(Certificate $certificate): StreamedResponse
    {
        $this->authorize('download', $certificate);

        abort_unless(
            Storage::disk('private')->exists($certificate->pdf_path),
            Response::HTTP_NOT_FOUND
        );

        return Storage::disk('private')->download(
            $certificate->pdf_path,
            'certificate-'.$certificate->id.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
