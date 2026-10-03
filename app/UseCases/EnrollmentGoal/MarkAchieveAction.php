<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 個人目標を達成済みにするユースケース。
 */
final class MarkAchieveAction
{
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        return DB::transaction(function () use ($goal) {
            $goal->update([
                'achieved_at' => now(),
            ]);

            return $goal->fresh();
        });
    }
}
