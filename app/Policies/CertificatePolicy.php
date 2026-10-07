<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

/**
 * 修了証 PDF 出力の認可ルール。
 * 　受講生本人 — 自分の修了証のみダウンロード可、他受講生の修了証は不可
 * 　コーチ — 自分の担当資格に紐づく修了証のみダウンロード可、担当外資格は不可
 * 　管理者 — すべての修了証をダウンロード可
 * 　学習中以外のステータス(修了 / 退会前)の受講生でも、本人の修了証はダウンロードできる(この処理はRoute middlewareで実施)
 */
class CertificatePolicy
{
    public function download(User $user, Certificate $certificate): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Student => $certificate->user_id === $user->id,
            UserRole::Coach => $user->assignedCertifications()
                ->whereKey($certificate->certification_id)
                ->exists(),
        };
    }
}
