<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * admin 用の面談パック詳細を取得するユースケース。
 * 直近 20 件の購入履歴 / 支払い情報 / 作成者・更新情報を取得する。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $plan): MeetingPack
    {
        return $plan->load([
            'payments' => fn ($query) => $query->latest()->limit(20)->with('user'),
            'createdBy',
            'updatedBy',
        ]);
    }
}
