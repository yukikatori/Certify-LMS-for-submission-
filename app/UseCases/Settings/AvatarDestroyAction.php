<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * アバター画像を削除するユースケース。
 */
final class AvatarDestroyAction
{
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user) {
            // ストレージのファイル削除
            if ($user->avatar_url) {
                $path = parse_url($user->avatar_url, PHP_URL_PATH);
                $relativePath = str_replace('/storage/', '', $path);

                Storage::disk('public')->delete($relativePath);
            }

            $user->update(['avatar_url' => null]);
        });
    }
}
