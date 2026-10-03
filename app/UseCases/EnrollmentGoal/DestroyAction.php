<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 個人目標を 削除 するユースケース。
 */
final class DestroyAction
{
    public function __invoke(EnrollmentGoal $goal): void
    {
        DB::transaction(fn () => $goal->delete());
    }
}