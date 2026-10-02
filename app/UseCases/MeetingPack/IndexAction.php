<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用の面談パックマスタ一覧をフィルタ付きで取得するユースケース。
 * 公開中 → 下書き → アーカイブ の順で並び、同 status 内は最終更新の降順。
 */
final class IndexAction
{
    public function __invoke(
        User $viewer,
        ?string $keyword,
        ?MeetingPackStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = MeetingPack::query()->forUser($viewer);

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
            $query->orderByRaw("CASE status WHEN 'published' THEN 1 WHEN 'draft' THEN 2 WHEN 'archived' THEN 3 ELSE 4 END");
        }

        return $query
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
