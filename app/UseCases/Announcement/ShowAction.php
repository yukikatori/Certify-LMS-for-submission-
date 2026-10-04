<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;

/**
 * admin 用の お知らせ配信詳細を取得するユースケース。
 */
final class ShowAction
{
    public function __invoke(Announcement $announcement): Announcement
    {
        return $announcement->load(['targetCertification', 'targetUser', 'createdBy']);
    }
}
