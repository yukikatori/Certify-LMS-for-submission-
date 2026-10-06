# Certify LMS

マルチ資格対応の資格学習プラットフォームです。受講生は資格ごとの教材で学習し、演習問題・模擬試験で理解度を確かめながら、コーチの面談サポートを受けて資格取得を目指せます。

> プロジェクト構造・ドメインモデル・コードの読み進め方は [ONBOARDING.md](./ONBOARDING.md) を参照してください。

## 主な機能

| ロール | 機能 |
|---|---|
| 受講生（student） | 教材閲覧 / 演習問題・苦手分野ドリル / 模擬試験（分野別ヒートマップ・合格可能性スコア） / AI 相談（Gemini）/  面談予約 / チャット / 学習時間・進捗・ストリーク管理 / 修了証の受領 |
| コーチ（coach） | 教材・演習問題・模試の管理 / 担当受講生の進捗フォロー / 面談対応・面談メモ / チャット |
| 管理者（admin） | ユーザー招待・管理 / 資格・資格分類マスタ管理 / 資格へのコーチ割当 / 面談回数の付与 / 全体ダッシュボード |

## 動作環境

- Docker Desktop / Docker Compose
- 開発環境は Laravel Sail で構築します（PHP コンテナ・MySQL・Mailpit・phpMyAdmin を起動）

## 環境構築手順

### 1. リポジトリの clone

```bash
git clone <このリポジトリの URL>
cd <リポジトリ名>
```

### 2. 環境変数ファイルの作成

```bash
cp .env.example .env
```

`.env.example` は Sail 向けに設定済みのため、コピーするだけでローカル開発を始められます（外部サービス連携のキーは後述）。

### 3. 依存パッケージのインストール（初回のみ）

`vendor/` がまだ無いため、初回のみ Docker 経由で Composer を実行します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

### 4. Sail エイリアスの設定（推奨）

```bash
alias sail='./vendor/bin/sail'
```

以降のコマンドはこのエイリアス前提で記載します（未設定の場合は `./vendor/bin/sail` に読み替えてください）。

### 5. コンテナの起動

```bash
sail up -d
```

### 6. アプリケーションの初期化

```bash
sail artisan key:generate
sail artisan storage:link
sail artisan migrate:fresh --seed
```

`storage:link` は教材画像・プロフィール画像の配信に必要です。`migrate:fresh --seed` でテーブル作成とデモデータ投入が行われます（いつでも再実行してデータを初期状態に戻せます）。

### 7. フロントエンドのビルド

```bash
sail npm install
sail npm run build
```

Blade / CSS / JS を編集しながら開発する場合は、`build` の代わりに `sail npm run dev` を起動したままにしてください（Vite のホットリロードが効きます）。

### 8. 動作確認

http://localhost:8000 にアクセスし、下記の[ログインアカウント](#ログインアカウント)でログインできればセットアップ完了です。

## 開発環境 URL

| 用途 | URL |
|---|---|
| アプリケーション | http://localhost:8000 |
| phpMyAdmin（DB 確認） | http://localhost:8080 |
| Mailpit（メール確認） | http://localhost:8025 |

アプリケーションが送信するメール（招待メールなど）はすべて Mailpit に届きます。実際のメールは送信されません。

## ログインアカウント

`migrate:fresh --seed` 後、以下の固定アカウントが使えます（パスワードはすべて `password`）。

| ロール | メールアドレス | 備考 |
|---|---|---|
| 管理者 | admin@certify-lms.test | 全機能にアクセス可能 |
| コーチ | coach@certify-lms.test | IT 系資格の担当 / Google カレンダー連携済み |
| コーチ | coach2@certify-lms.test | ビジネス系資格の担当 / Google カレンダー未連携 |
| 受講生 | student@certify-lms.test | 受講中の資格・学習履歴・面談などのデモデータ付き |

このほか、ライフサイクル（招待中 / 受講中 / 卒業 / 退会）を網羅したデモユーザーが投入されます。

> 本サービスは**招待制**です。公開の会員登録画面はありません。新規ユーザーを作るには、管理者でログイン → ユーザー管理から招待 → Mailpit で招待メールの URL を開く → オンボーディング登録、という流れになります。

## AI 相談（Gemini）

受講中の受講生は、画面右下のフローティングウィジェットまたは `/ai-chat` から Gemini に学習相談できます。教材 Section 画面から開始した会話には閲覧中 Section の文脈が紐づき、それ以外の画面ではデフォルト受講資格の文脈で相談を開始します。

AI 相談は `AI_CHAT_ENABLED` で機能全体を ON/OFF できます。OFF の場合、関連 UI は表示されず、AI 相談ルートも利用できません。

Gemini API を実際に呼び出すには、`.env` に `GEMINI_API_KEY` を設定してください。未設定のままでも画面確認はできますが、メッセージ送信時には「Gemini API キーが未設定」の案内が表示されます。

```env
AI_CHAT_ENABLED=true
AI_CHAT_DAILY_MESSAGE_LIMIT=50

GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.6-flash
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_TIMEOUT=30
```

## 面談リマインダー通知

予約済み面談には、前日と開始 1 時間前にアプリ内通知とメールを送信します。定期実行は `app/Console/Kernel.php` に登録済みです。

ローカルで定期実行を待たずに確認する場合は、既存の面談データの `scheduled_at` を翌日または 1 時間後に調整してから、以下のコマンドを手動実行してください。追加の初期データ投入は不要です。

```bash
sail artisan notifications:send-meeting-reminders --window=eve
sail artisan notifications:send-meeting-reminders --window=one_hour_before
```

## テスト

```bash
sail artisan test                  # 全テスト実行
sail artisan test --filter=Xxx    # クラス名・メソッド名で絞り込み
```

## コード整形

Laravel Pint を使用しています。コミット前に実行してください。

```bash
sail bin pint --dirty    # 変更ファイルのみ整形
sail bin pint --test     # 整形漏れの確認（CI 相当のチェック）
```

## 使用技術

- PHP 8.5 / Laravel 10
- MySQL 8.4
- Laravel Fortify（認証）/ Laravel Sanctum（API 認証）
- Blade + Tailwind CSS + Vite（JavaScript は素の JS、フレームワーク不使用）
- PHPUnit / Laravel Pint
- league/commonmark（教材本文の Markdown レンダリング）
- Pusher（チャットのリアルタイム配信）
- Docker（Laravel Sail）

## 環境変数

`.env.example` をコピーするだけで、すべての機能がローカルで動作します（メールは Mailpit に配信されます）。

- `PUSHER_*` — チャットのリアルタイム配信に使用します。有効にする場合は Pusher のキーを取得して設定し、`BROADCAST_DRIVER=pusher` に変更してください。未設定（既定の `BROADCAST_DRIVER=log`）でもメッセージの送受信自体は動作し、相手画面へのリアルタイム反映のみ行われません
- `GOOGLE_CALENDAR_CLIENT_ID` / `GOOGLE_CALENDAR_CLIENT_SECRET` / `GOOGLE_CALENDAR_REDIRECT_URI` — コーチの Google カレンダー連携に使用します。実際に Google API と通信する場合は Google Cloud Console で OAuth クライアントを作成し、redirect URI に `http://localhost:8000/settings/google-calendar/callback` を登録してください

新しい環境変数やセットアップ手順を追加した場合は、`.env.example` と本 README に追記し、チームの誰でも環境を再現できる状態を保ってください。

## Google カレンダー連携

コーチは面談設定画面から、自分の Google アカウントを任意で連携できます。連携済みコーチの場合、Google カレンダー上で予定が入っている時間帯は、受講生の面談予約画面の空き枠から除外されます。予約成立時にはコーチのプライマリカレンダーへ面談予定を作成し、面談キャンセル時には LMS が作成した予定を削除します。

未連携コーチは従来通り、LMS 内の面談可能時間枠と既存予約のみで空き判定します。Google API との通信に失敗した場合も、面談予約・キャンセル・空き枠表示は停止せず、LMS 内の情報をもとに処理を継続します。

OAuth では以下のスコープを利用します。

- `https://www.googleapis.com/auth/calendar`
- `https://www.googleapis.com/auth/userinfo.email`
- `https://www.googleapis.com/auth/userinfo.profile`

`migrate:fresh --seed` 後は `coach@certify-lms.test` が Google カレンダー連携済み、`coach2@certify-lms.test` が未連携の状態になります。ただし、Seeder のトークンは開発確認用のダミー値です。実際に Google API と通信する確認を行う場合は、コーチ本人でログインし、面談設定画面から OAuth 連携をやり直してください。

現在の実装では、Google OAuth の access token / refresh token を DB に保存します。本番運用では、トークンの暗号化保存を推奨します。
