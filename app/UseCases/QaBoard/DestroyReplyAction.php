<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板の回答を削除するユースケース。
 */
final class DestroyReplyAction
{
    public function __invoke(QaReply $reply): void
    {
        DB::transaction(fn () => $reply->delete());
    }
}
