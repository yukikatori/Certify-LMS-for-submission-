<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 受講生メモを更新するユースケース。
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(EnrollmentNote $note, User $user, array $validated): EnrollmentNote
    {
        return DB::transaction(function () use ($note, $validated) {
            $note->update([
                'body' => $validated['body'],
            ]);

            return $note->fresh();
        });
    }
}
