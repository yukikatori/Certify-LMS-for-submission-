<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Enums\UserStatus;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用のプランマスタ一覧をフィルタ付きで取得するユースケース。
 * 公開中 → 下書き → アーカイブ の順で並び、同 status 内は最終更新の降順。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?PlanStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Plan::query()
            ->withCount([
                'users' => fn ($query) => $query->where('status', UserStatus::InProgress->value),
            ]);

        if ($keyword !== null && $keyword !== '') {
            $query->where('name', 'LIKE', '%'.$keyword.'%');
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $query->orderByRaw("FIELD(status, 'published', 'draft', 'archived')");
        } else {
            // SQLite では FIELD() が使えないため CASE 式で同等の優先順位を表現する
            $query->orderByRaw(
                "CASE status WHEN 'published' THEN 1 WHEN 'draft' THEN 2 WHEN 'archived' THEN 3 ELSE 4 END"
            );
        }

        return $query
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
