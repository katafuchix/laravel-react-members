# React スターターキットでデフォルトで実装される会員機能

`laravel new --react`（Laravel 13.43.0、Laravel 標準認証 = Fortify）で生成した直後のコードを確認した結果。
キットのコードには手を加えていない状態。

## 一覧

| 機能 | 状態 | 備考 |
|---|---|---|
| 会員登録 | 有効 | 名前、メール、パスワード、確認用パスワード |
| ログイン / ログアウト | 有効 | メール + パスワード |
| ログイン状態の保持（Remember me） | 有効 | `users.remember_token` を使用 |
| パスワードリセット | 有効 | メールでリンクを送信 |
| メール認証 | 有効 | 認証前は `/dashboard` に入れない |
| 認証メールの再送 | 有効 | 認証画面のボタンから |
| 2要素認証（TOTP） | 有効 | QR コード、リカバリーコード。有効化時に確認コードの入力が必要 |
| パスキー | 有効 | パスキーでのログイン、登録、削除 |
| パスワード再確認 | 有効 | 重要な操作の前にパスワードを再入力させる |
| プロフィール変更 | 有効 | 名前、メール |
| パスワード変更 | 有効 | 現在のパスワードが必要。1分に6回まで |
| アカウント削除 | 有効 | 削除後にログアウトされる |
| 外観設定 | 有効 | ライト / ダーク切り替え |
| ログイン試行の制限 | 有効 | 後述 |
| ダッシュボード | 有効 | 中身は空のプレースホルダー |

## 各機能の詳細

### 会員登録

- 入力項目は 名前（必須、255文字以内）、メール（必須、重複不可）、パスワード、確認用パスワード。
- 登録後は自動的にログインされ、メール認証画面（`/email/verify`）へ移る。
- 登録処理は `app/Actions/Fortify/CreateNewUser.php`。項目を増やす場合は、ここを編集する。

### パスワードの強度

| 環境 | ルール |
|---|---|
| 本番（`APP_ENV=production`） | 12文字以上、英大文字・小文字、数字、記号を含む、漏えい済みパスワードは不可 |
| 本番以外 | 緩い（Laravel の標準の最小ルール） |

- 設定は `app/Providers/AppServiceProvider.php` の `Password::defaults()`。

### ログイン

- ログイン画面にメール + パスワード、「Remember me」、パスワードリセットへのリンク、パスキーでのログインがある。
- ログイン後の遷移先は `/dashboard`（`config/fortify.php` の `home`）。
- 2要素認証を有効にしたユーザーは、ログイン後に `/two-factor-challenge` で確認コードを入力する。

### ログイン試行の制限（Devise の `lockable` との違い）

| 対象 | 制限 |
|---|---|
| ログイン | メールアドレスと IP の組み合わせごとに、1分に5回まで |
| 2要素認証の確認 | セッションごとに、1分に5回まで |
| パスキー | 1分に10回まで |

- 一定時間のリクエスト制限であり、**アカウントそのものをロックする機能ではない**。
- Devise の `lockable`（失敗回数でロックし、メールやタイムアウトで解除）に相当する機能は、デフォルトにはない。

### パスワードリセット

- 「Forgot your password?」からメールアドレスを入力すると、リセット用リンクがメールで届く。
- リセットリンクの有効期限は60分、再送の間隔は60秒（`config/auth.php` の `passwords.users`）。

### メール認証

- `User` モデルが `MustVerifyEmail` を実装している。
- `/dashboard`、アカウント削除、パスワード変更、外観設定、セキュリティ設定は、認証済みのユーザーだけが使える。
- 認証リンクは署名付き URL で、有効期限がある。
- プロフィール画面でメールアドレスを変更すると、`email_verified_at` が空に戻り、再認証が必要になる。
- 認証リンクの有効期限は Laravel のデフォルト（60分）。`config/auth.php` に `verification.expire` のキーはないため、変更する場合は追加する。

### 2要素認証

- 「Security」画面で有効化する。QR コードを認証アプリで読み取り、確認コードを入力して有効になる。
- リカバリーコードの表示と再生成ができる。
- 設定は `config/fortify.php` の `twoFactorAuthentication`（`confirm`: 有効化時に確認コードが必要、`confirmPassword`: 操作前にパスワード再確認）。

### パスキー

- 「Security」画面で登録、削除ができる。
- ログイン画面の「Sign in with a passkey」から、パスワードなしでログインできる。
- パスキー用のテーブルは `2024_01_01_000000_create_passkeys_table` で作られる。

### パスワード再確認

- 「Security」画面に入るときは、パスワードの再入力が必要（`RequirePassword` ミドルウェア）。
- 再確認の有効時間は10800秒（3時間）。`AUTH_PASSWORD_TIMEOUT` で変更できる。

### 設定画面（`/settings`）

| 画面 | パス | 内容 |
|---|---|---|
| プロフィール | `/settings/profile` | 名前、メールの変更、アカウント削除 |
| セキュリティ | `/settings/security` | パスワード変更、2要素認証、パスキー |
| 外観 | `/settings/appearance` | ライト / ダークの切り替え |

## 画面（`resources/js/pages/`）

| 区分 | ファイル |
|---|---|
| 認証 | `auth/login.tsx`、`auth/register.tsx`、`auth/forgot-password.tsx`、`auth/reset-password.tsx`、`auth/verify-email.tsx`、`auth/confirm-password.tsx`、`auth/two-factor-challenge.tsx` |
| 設定 | `settings/profile.tsx`、`settings/security.tsx`、`settings/appearance.tsx` |
| その他 | `welcome.tsx`、`dashboard.tsx` |

## ルート一覧（`php artisan route:list` の結果）

| 区分 | メソッド | パス | 名前 |
|---|---|---|---|
| 登録 | GET / POST | `/register` | `register` / `register.store` |
| ログイン | GET / POST | `/login` | `login` / `login.store` |
| ログアウト | POST | `/logout` | `logout` |
| パスワードリセット | GET / POST | `/forgot-password` | `password.request` / `password.email` |
| パスワードリセット | GET / POST | `/reset-password/{token}`、`/reset-password` | `password.reset` / `password.update` |
| メール認証 | GET | `/email/verify` | `verification.notice` |
| メール認証 | GET | `/email/verify/{id}/{hash}` | `verification.verify` |
| メール認証の再送 | POST | `/email/verification-notification` | `verification.send` |
| パスワード再確認 | GET / POST | `/user/confirm-password` | `password.confirm` / `password.confirm.store` |
| 2要素認証 | GET / POST | `/two-factor-challenge` | `two-factor.login` / `two-factor.login.store` |
| 2要素認証の管理 | POST / DELETE | `/user/two-factor-authentication` | `two-factor.enable` / `two-factor.disable` |
| パスキー | POST | `/passkeys/login` ほか | `passkey.login` など |
| ダッシュボード | GET | `/dashboard` | `dashboard` |
| 設定 | GET / PATCH / DELETE | `/settings/profile` | `profile.edit` / `profile.update` / `profile.destroy` |
| 設定 | GET / PUT | `/settings/security`、`/settings/password` | `security.edit` / `user-password.update` |
| 設定 | GET | `/settings/appearance` | `appearance.edit` |

## データベース（`users` テーブル）

| カラム | 内容 |
|---|---|
| `id` | 主キー |
| `name` | 名前 |
| `email` | メール（ユニーク） |
| `email_verified_at` | メール認証日時（未認証は空） |
| `password` | パスワード（ハッシュ） |
| `remember_token` | ログイン状態の保持用 |
| `created_at` / `updated_at` | 作成日時 / 更新日時 |
| （2要素認証用のカラム） | `add_two_factor_columns_to_users_table` で追加 |

関連テーブル: `password_reset_tokens`（リセット用）、`sessions`（セッション。`SESSION_DRIVER=database`、有効時間 120 分）、`passkeys`、`jobs`、`cache`

## 付属のテスト（PHPUnit）

| 区分 | ファイル |
|---|---|
| 認証 | `AuthenticationTest`、`RegistrationTest`、`EmailVerificationTest`、`VerificationNotificationTest`、`PasswordResetTest`、`PasswordConfirmationTest`、`TwoFactorChallengeTest` |
| 設定 | `ProfileUpdateTest`、`SecurityTest` |
| その他 | `DashboardTest`、`ExampleTest` |

## Devise との対応

| Devise | このキット | 備考 |
|---|---|---|
| `:database_authenticatable` | あり | ログイン、パスワード変更 |
| `:registerable` | あり | 会員登録、プロフィール変更、アカウント削除 |
| `:recoverable` | あり | パスワードリセット |
| `:rememberable` | あり | Remember me |
| `:confirmable` | あり | メール認証（`MustVerifyEmail`） |
| `:validatable` | あり | メール形式とパスワード強度の検証 |
| `:lockable` | **なし** | リクエスト制限のみ。アカウントロックはない |
| `:trackable` | **なし** | ログイン回数や最終ログイン日時は記録されない |
| `:timeoutable` | **なし** | セッション有効時間（120分）はあるが、操作がない場合のタイムアウトは別機能 |
| `:omniauthable` | **なし** | SNS ログインは Socialite を別途導入する |
| 2要素認証 | あり | Devise では別 gem が必要 |
| パスキー | あり | Devise では別 gem が必要 |

## デフォルトにないもの（別途実装が必要）

- 管理者などの権限（ロール）
- 会員ごとの追加項目（電話番号、プロフィール画像など）
- アカウントのロック
- ログイン履歴、最終ログイン日時
- SNS ログイン
- 画面の日本語化（画面の文言は英語）
- 管理画面

## 参考

- [Starter Kits](https://laravel.com/framework/docs/starter-kits)
- [Email Verification](https://laravel.com/framework/docs/verification)
