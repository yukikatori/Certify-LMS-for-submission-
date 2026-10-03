<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 個人目標を更新するユースケース。
 */
final class UpdateAction
{
    /**
     * @param array{
     *     title: string,
     *     target_date: string,
     *     description?: ?string
     * } $validated
     */
    public function __invoke(EnrollmentGoal $goal, array $validated): EnrollmentGoal
    {
        return DB::transaction(function () use ($goal, $validated) {
            $goal->update([
                'title' => $validated['title'],
                'target_date' => $validated['target_date'],
                'description' => $validated['description'] ?? null,
            ]);

            return $goal->fresh();
        });
    }
}
