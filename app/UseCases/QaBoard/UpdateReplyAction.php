<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板の質問を更新するユースケース。
 */
final class UpdateReplyAction
{
    public function __invoke(QaReply $reply, array $validated): QaReply
    {
        return DB::transaction(function () use ($reply, $validated) {
            $reply->update([
                'body' => $validated['body'],
            ]);

            return $reply->fresh();
        });
    }
}
