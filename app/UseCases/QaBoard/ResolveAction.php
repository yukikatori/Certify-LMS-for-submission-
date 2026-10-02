<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板の質問を解決済に変更するユースケース。
 */
final class ResolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return DB::transaction(function () use ($thread) {
            $thread->update([
                'status' => QaThreadStatus::Resolved->value,
            ]);

            return $thread->fresh();
        });
    }
}
