<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Enums\UserRole;
use App\Exceptions\QaBoard\QaThreadNotDeletableException;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板の質問を削除するユースケース。
 * 投稿者本人は回答がついていないスレッドのみ削除できる。
 * 管理者は任意のスレッドを削除できる。
 */
final class DestroyAction
{
    /**
     * @throws QaThreadNotDeletableException
     *                                       投稿者本人は回答がついているスレッドを削除できない
     *                                       管理者は任意のスレッドを削除できる
     */
    public function __invoke(User $user, QaThread $thread): void
    {
        if ($user->role !== UserRole::Admin && ($thread->replies()->exists() || $thread->user_id !== $user->id)) {
            throw new QaThreadNotDeletableException;
        }

        DB::transaction(fn () => $thread->delete());
    }
}
