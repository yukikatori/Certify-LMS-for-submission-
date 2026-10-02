<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板の質問への回答を新規作成するユースケース。
 */
final class StoreReplyAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(User $user, QaThread $thread, array $validated): QaReply
    {
        return DB::transaction(function () use ($user, $thread, $validated) {
            return $thread->replies()->create([
                'user_id' => $user->id,
                'body' => $validated['body'],
            ]);
        });
    }
}
