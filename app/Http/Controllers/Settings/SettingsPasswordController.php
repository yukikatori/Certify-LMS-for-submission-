<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * プロフィール設定画面からのパスワード変更 Controller。
 *
 * 入力検証とハッシュ更新は Fortify 公式パターンの UpdateUserPassword Action に委譲する。
 */
class SettingsPasswordController extends Controller
{
    public function update(Request $request, UpdateUserPassword $action): RedirectResponse
    {
        $action->update($request->user(), $request->only([
            'current_password',
            'password',
            'password_confirmation',
        ]));

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'password'])
            ->with('status', 'パスワードを更新しました。');
    }
}
