<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Support\Facades\DB;

/**
 * 受講生メモを削除するユースケース。
 */
final class DestroyAction
{
    public function __invoke(EnrollmentNote $note): void
    {
        DB::transaction(fn () => $note->delete());
    }
}
