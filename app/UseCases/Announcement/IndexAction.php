<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用の お知らせ配信一覧を取得するユースケース。
 */
final class IndexAction
{
    public function __invoke(): LengthAwarePaginator
    {
        return Announcement::query()
            ->with(['targetCertification', 'targetUser', 'createdBy'])
            ->latest('dispatched_at')
            ->latest()
            ->paginate(20);
    }
}
