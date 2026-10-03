<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\UserStatus;
use App\Models\Plan;

/**
 * admin 用のプランマスタ詳細を取得するユースケース。
 * プランに紐づくユーザー情報を Eager Loading で揃える。
 */
final class ShowAction
{
    public function __invoke(Plan $plan): Plan
    {
        return $plan
            ->load([
                'users' => fn ($query) => $query
                    ->where('status', UserStatus::InProgress->value)
                    ->orderBy('plan_expires_at'),
                'createdBy',
                'updatedBy',
            ])
            ->loadCount([
                'users' => fn ($query) => $query->where('status', UserStatus::InProgress->value),
            ]);
    }
}
