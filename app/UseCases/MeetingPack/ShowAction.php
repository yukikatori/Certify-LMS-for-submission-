<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;
use Illuminate\Support\Collection;

/**
 * admin 用の面談パック詳細を取得するユースケース。
 * 作成者・更新者を取得し、購入履歴は Payment 実装が入るまでは空コレクションとして扱う。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $plan): MeetingPack
    {
        $plan->load(['createdBy', 'updatedBy']);
        $plan->setRelation('payments', new Collection);

        return $plan;
    }
}
