<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 受講生メモを新規作成するユースケース。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(User $user, Enrollment $enrollment, array $validated): EnrollmentNote
    {
        return DB::transaction(fn () => EnrollmentNote::create([
            'user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'body' => $validated['body'],
        ]));
    }
}
