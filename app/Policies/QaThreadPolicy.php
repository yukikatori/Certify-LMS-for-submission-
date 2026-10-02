<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板投稿の認可ルール
 * - 受講生は公開済資格すべてのスレッドを閲覧・投稿できる
 * - コーチは担当資格のスレッドのみ閲覧・回答でき、担当外の資格は操作できない
 * - 公開停止中の資格のスレッドは受講生・コーチには見えない(管理者は閲覧できる)
 * - 受講中の受講生・コーチのみアクセスできる
 * - 管理者は専用画面から、公開停止中の資格を含む全資格のスレッドを横断的に閲覧できる
 */
class QaThreadPolicy
{
    /**
     * 質問掲示板の一覧表示、受講生 / コーチ / 管理者がアクセス可
     */
    public function viewAny(User $auth): bool
    {
        return in_array($auth->role, [UserRole::Student, UserRole::Coach, UserRole::Admin], true);
    }

    /**
     * 質問掲示板の質問詳細表示、受講生 / コーチ / 管理者がアクセス可
     */
    public function view(User $auth, QaThread $thread): bool
    {
        return match ($auth->role) {
            UserRole::Coach => $thread->certification->coaches->contains('id', $auth->id)
                && $thread->certification->status === CertificationStatus::Published,
            UserRole::Student => $thread->certification->status === CertificationStatus::Published,
            UserRole::Admin => true,
        };
    }

    /**
     * 質問掲示板の質問作成ページの表示、受講生がアクセス可
     */
    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Student;
    }

    /**
     * 質問掲示板の質問編集ページの表示、投稿者がアクセス可
     */
    public function update(User $auth, QaThread $thread): bool
    {
        return $auth->id === $thread->user->id && $thread->certification->status === CertificationStatus::Published;
    }

    /**
     * 質問掲示板の質問削除、投稿者 / 管理者がアクセス可
     */
    public function delete(User $auth, QaThread $thread): bool
    {
        return match ($auth->role) {
            UserRole::Student => $auth->id === $thread->user->id
                && $thread->certification->status === CertificationStatus::Published,
            UserRole::Admin => true,
            UserRole::Coach => false,
        };
    }

    /**
     * 質問掲示板の質問を解決済へ変更、投稿者がアクセス可
     */
    public function resolve(User $auth, QaThread $thread): bool
    {
        return $auth->id === $thread->user->id && $thread->certification->status === CertificationStatus::Published;
    }

    /**
     * 質問掲示板の質問を未解決へ変更、投稿者がアクセス可
     */
    public function unresolve(User $auth, QaThread $thread): bool
    {
        return $auth->id === $thread->user->id && $thread->certification->status === CertificationStatus::Published;
    }
}
