<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Enums\UserStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * プランマスタを削除するユースケース。下書き状態以外かつ受講生が紐づいているプランは削除不可
 */
final class DestroyAction
{
    /**
     * @throws PlanNotDeletableException
     * 下書き状態以外かつ受講生が紐づいているプランは削除不可
     */
    public function __invoke(Plan $plan): void
    {
        // 下書き状態以外だと削除不可
        if ($plan->status !== PlanStatus::Draft) {
            throw new PlanNotDeletableException;
        }

        // 受講生が紐づいていると削除不可
        if ($plan->users()->where('status', UserStatus::InProgress->value)->exists()) {
            throw new PlanNotDeletableException;
        }

        DB::transaction(fn () => $plan->delete());
    }
}
