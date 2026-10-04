<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * プロフィールを更新するユースケース。氏名と自己紹介を更新。
 * コーチのみ固定面談 URLの更新可能
 */
final class ProfileUpdateAction
{
    /**
     * @param array{name: string, bio?: ?string, meeting_url?: ?url} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        return DB::transaction(function () use ($user, $validated) {
            $updateData = [
                'name' => $validated['name'],
                'bio' => $validated['bio'] ?? null,
            ];

            // コーチのみ meeting_url を更新
            if ($user->role === UserRole::Coach) {
                $updateData['meeting_url'] = $validated['meeting_url'] ?? null;
            }

            $user->update($updateData);

            return $user->fresh();
        });
    }
}
