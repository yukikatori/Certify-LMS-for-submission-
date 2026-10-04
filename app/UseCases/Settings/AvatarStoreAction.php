<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * アバター画像を新規登録するユースケース。
 */
final class AvatarStoreAction
{
    /**
     * @param array{avatar: UploadedFile} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        return DB::transaction(function () use ($user, $validated) {
            // 古い画像があれば削除
            if ($user->avatar_url) {
                $path = parse_url($user->avatar_url, PHP_URL_PATH);
                $relativePath = str_replace('/storage/', '', $path);

                Storage::disk('public')->delete($relativePath);
            }

            $path = $validated['avatar']->store('avatars', 'public');
            $url = Storage::disk('public')->url($path);

            $user->update(['avatar_url' => $url]);

            return $user->fresh();
        });
    }
}
