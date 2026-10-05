<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Google Calendar 連携の確認用初期データ。
 *
 * 固定コーチのうち 1 名だけを連携済みにし、もう 1 名は未連携のまま残す。
 * これにより、面談設定画面での連携状態表示・解除操作と、
 * 予約画面で連携済み / 未連携コーチが混在する状態を確認できる。
 *
 * トークンは開発用のダミー値。実 Google API 連携を確認する場合は、
 * コーチ本人でログインして OAuth 連携をやり直すこと。
 */
class GoogleCalendarConnectionSeeder extends Seeder
{
    public function run(): void
    {
        $coach = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->first();

        if (! $coach) {
            $this->command?->warn('GoogleCalendarConnectionSeeder: coach@certify-lms.test が見つかりません。先に UserSeeder を実行してください。');

            return;
        }

        GoogleCalendarConnection::query()->updateOrCreate(
            ['coach_id' => $coach->id],
            [
                'google_account_id' => 'demo-google-account-coach-taro',
                'google_email' => 'coach.taro.google@example.com',
                'access_token' => 'demo-access-token',
                'refresh_token' => 'demo-refresh-token',
                'token_expires_at' => now()->addYear(),
                'connected_at' => now()->subDay(),
            ],
        );
    }
}
