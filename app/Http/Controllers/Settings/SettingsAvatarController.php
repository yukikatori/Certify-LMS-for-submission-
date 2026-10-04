<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AvatarStoreRequest;
use App\UseCases\Settings\AvatarDestroyAction;
use App\UseCases\Settings\AvatarStoreAction;
use Illuminate\Http\RedirectResponse;

/**
 * アバター画像(全ロール)
 *  - アイコン画像をアップロード
 *  - アイコン画像を削除すると、アイコン未設定の表示に戻る
 */
class SettingsAvatarController extends Controller
{
    public function store(AvatarStoreRequest $request, AvatarStoreAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'アイコン画像を登録しました。');
    }

    public function destroy(AvatarDestroyAction $action): RedirectResponse
    {
        $action(auth()->user());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'アイコン画像を削除しました。');
    }
}
