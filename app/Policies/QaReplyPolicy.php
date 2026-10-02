<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板回答の認可ルール
 * - 受講生は公開済資格すべてのスレッドを閲覧・投稿できる
 * - コーチは担当資格のスレッドのみ閲覧・回答でき、担当外の資格は操作できない
 * - 公開停止中の資格のスレッドは受講生・コーチには見えない(管理者は閲覧できる)
 * - 受講中の受講生・コーチのみアクセスできる
 * - 管理者は専用画面から、公開停止中の資格を含む全資格のスレッドを横断的に閲覧できる
 */
class QaReplyPolicy
{
    /**
     * 質問掲示板の質問への回答作成、受講生/コーチがアクセス可
     */
    public function create(User $auth, QaThread $thread): bool
    {
        return match ($auth->role) {
            UserRole::Coach => $thread->certification->coaches->contains('id', $auth->id)
                && $thread->certification->status === CertificationStatus::Published,
            UserRole::Student => $thread->certification->status === CertificationStatus::Published,
            UserRole::Admin => false,
        };
    }

    /**
     * 質問掲示板の質問への回答編集ページの表示、投稿者がアクセス可
     */
    public function update(User $auth, QaReply $reply): bool
    {
        return match ($auth->role) {
            UserRole::Student => $auth->id === $reply->user_id
                && $reply->thread->certification->status === CertificationStatus::Published,
            UserRole::Coach => $auth->id === $reply->user_id
                && $reply->thread->certification->coaches->contains('id', $auth->id)
                && $reply->thread->certification->status === CertificationStatus::Published,
            UserRole::Admin => false,
        };
    }

    /**
     * 質問掲示板の質問への回答削除、投稿者 / 管理者がアクセス可
     */
    public function delete(User $auth, QaReply $reply): bool
    {
        return match ($auth->role) {
            UserRole::Student => $auth->id === $reply->user_id
                && $reply->thread->certification->status === CertificationStatus::Published,
            UserRole::Coach => $auth->id === $reply->user_id
                && $reply->thread->certification->coaches->contains('id', $auth->id)
                && $reply->thread->certification->status === CertificationStatus::Published,
            UserRole::Admin => true,
        };
    }
}
