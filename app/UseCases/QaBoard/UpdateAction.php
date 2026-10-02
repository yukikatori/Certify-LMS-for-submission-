<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板の質問を更新するユースケース。`status` は本 Action では更新せず、公開状態遷移用 Action に責務分離する。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, body: string} $validated
     */
    public function __invoke(QaThread $thread, array $validated): QaThread
    {
        return DB::transaction(function () use ($thread, $validated) {
            $thread->update([
                'title' => $validated['title'],
                'body' => $validated['body'],
            ]);

            return $thread->fresh();
        });
    }
}
