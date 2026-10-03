<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * @param array{
     *     title: string,
     *     target_date: string,
     *     description?: ?string
     * } $validated
     */
    public function __invoke(User $user, Enrollment $enrollment, array $validated): EnrollmentGoal
    {
        return DB::transaction(fn () => EnrollmentGoal::create([
            'user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'title' => $validated['title'],
            'target_date' => $validated['target_date'],
            'description' => $validated['description'] ?? null,
            'achieved_at' => null,
        ]));
    }
}
