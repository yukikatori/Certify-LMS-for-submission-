<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\UseCases\Settings\ProfileUpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * プロフィール表示・編集のController
 *
 * プロフィール表示・編集(全ロール)
 * - 本人の氏名 / メール / 自己紹介 / アイコン画像 / ロール / アカウント状態を確認できる
 * - 氏名と自己紹介を編集できる。メールはこの画面からは変更できない
 * - 修了済受講生もこの画面を利用できる
 *
 * コーチ固定面談 URL(コーチのみ)
 * - コーチは自分の編集フォームから固定面談 URL を更新できる
 * - 受講生 / 管理者には固定面談 URL の入力欄が現れず、操作もできない
 */
class SettingsProfileController extends Controller
{
    public function edit(): View
    {
        return view('settings.profile', [
            'user' => auth()->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request, ProfileUpdateAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());
        $user = auth()->user();

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'プロフィールを更新しました。');
    }
}
