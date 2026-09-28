<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\EnrollmentStatus;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 予約画面のエントリポイント(URL に Enrollment 無し)のユースケース
 * `resolve-default-enrollment` Middleware が default 資格に redirect するため、
 * 本 method に到達するのは default 未設定 + 残存 Enrollment が 0 件 or 2+ 件のケース。
 */
final class CreateFallbackAction
{
    public function __invoke(User $user): Collection
    {
        return $enrollments = $user
            ?->enrollments()
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->with('certification')
            ->get();
    }
}
