# Laravel + React 会員制システム（Devise 相当）構築メモ

Rails の Devise と同様の会員制システムを、Laravel 公式スターターキット（React）で構築する手順書。
1ステップずつ確認しながら進める。

## 要件

- Laravel 公式スターターキットで、Devise 相当の会員制システムを構築する
- フロントエンドは React スターターキット
- 認証メールは Resend で送信する
- 1つ1つ確認しながら進める

## 環境（Step 0 の結果 / 2026-11-21 時点）

| 項目 | 状態 |
|---|---|
| PHP | 8.5.12 |
| Composer | 2.10.2 |
| Node / npm | v22.23.1 / 10.9.8 |
| Laravel インストーラ | 未インストール |
| 作業ディレクトリ | `laravel-react-members`（空） |

## 調査で分かったこと

### スターターキット

- React キットは React 19 + TypeScript + Inertia + shadcn/ui 構成。
- 認証は **Laravel Fortify** が担当する。
  - ログイン、登録、パスワードリセット、メール認証、2FA を最初から備える。Devise とほぼ同等。
- メール認証は次の2点で有効になる。
  - `User` モデルで `MustVerifyEmail` を実装する。
  - ルートに `verified` ミドルウェアを付ける。
- 機能を無効化する場合は、React 側のルート参照も消す。
  - ルート参照は Wayfinder による型付きルーティングなので、残るとビルドが失敗する。
- `laravel new` の選択肢として、Laravel 標準認証のほかに WorkOS AuthKit もある。
  - 今回は Devise 相当を自前で持ちたいので、標準認証を選ぶ。

### Resend の導入方法

| 方法 | パッケージ | 特徴 |
|---|---|---|
| Laravel 標準ドライバ（推奨） | `resend/resend-php` | `config/mail.php` と `config/services.php` の設定のみ |
| Resend 公式ガイド | `resend/resend-laravel` | ファサードや API の追加機能が使える |

- 追加機能が不要なので、標準ドライバ + `resend/resend-php` を推奨する。
- どちらも Resend の API キーと、検証済みの送信ドメインが必要。

## Laravel のバージョン

| バージョン | PHP 要件 | リリース | バグ修正 | セキュリティ修正 |
|---|---|---|---|---|
| **13（最新・採用）** | 8.3〜8.5 | 2026-03-17 | 2027 Q3 まで | 2028-03-17 まで |
| 12 | 8.2〜8.5 | 2025 | 2026-08-13 で終了 | 2027-02-24 まで |
| 11 | - | - | - | 2026-03-12 で終了 |

- 新規作成なので **Laravel 13** を採用する。
  - 環境の PHP 8.5.12 に対応している。
  - サポート期間が最も長い。Laravel 12 はバグ修正がすでに終了している。
- 現環境で `laravel new` を実行すると、自動的に最新の Laravel 13 になる。
- React スターターキットは Laravel 12 で導入された機能。Breeze と Jetstream は、それ以降更新されていない。
- Laravel 13 のスターターキットの変更点は未確認。Step 2 の前に、公式ドキュメントで React キットの構成（Inertia のバージョン、認証まわり）を確認する。

## 進め方

各ステップの完了ごとに確認を取ってから次へ進む。

| Step | 内容 | 状態 |
|---|---|---|
| 0 | 環境確認・調査 | 完了 |
| 1 | Laravel インストーラの導入（`composer global require laravel/installer`） | 完了（v5.33.0。PATH 未設定のためフルパスで実行） |
| 2 | `laravel new` でプロジェクト作成（React キット、Laravel 標準認証） | 完了（Laravel 13.43.0、Boost 導入済み） |
| 3 | 初期状態の動作確認（`composer run dev`、登録・ログイン画面） | 完了（画面表示とテストユーザー登録を確認） |
| 4 | メール認証の確認（`MustVerifyEmail`、`verified` ミドルウェアはキットで設定済み。動作確認が中心） | 完了（`MAIL_MAILER=log` で認証フローを確認） |
| 5 | Resend の導入（パッケージ、`.env`、`config/services.php`、`config/mail.php`） | 完了（設定の反映まで。実送信は Step 6 で確認） |
| 6 | 認証メールの送信テスト | 完了（Resend 経由でメール受信、リンクで認証完了を確認） |

### 決定前の確認事項

- Step 1: 解決済み。インストーラは `~/.composer/vendor/bin/laravel`。`~/.zshrc` の PATH は変更せず、フルパスで実行する。
- Step 2: 決定済み。DB は **SQLite**、テストは **PHPUnit**。
- Step 5: Resend の API キーと、検証済みの送信ドメインを用意できるか。

## Step 2 の実行記録

実行したコマンド（親ディレクトリから実行）:

```
laravel new laravel-react-members --react --database=sqlite --phpunit --npm --boost --force --no-interaction
```

- `laravel new .` はカレントディレクトリでは `--force` が使えず、`.` 指定のままだと「Application already exists!」になる。
  - そのため、親ディレクトリからプロジェクト名を指定し、`--force` を付けて実行した。
  - `docs/` は実行中だけ退避し、実行後に元へ戻した。

生成されたバージョン:

| 項目 | バージョン |
|---|---|
| Laravel Framework | 13.43.0 |
| Fortify | ^1.37.2 |
| Inertia (Laravel) / @inertiajs/react | ^3.0 / ^3.0.0 |
| React / TypeScript | ^19.2.0 / ^5.7.2 |
| Wayfinder | ^0.1.14 |
| Boost | ^2.2 |
| PHPUnit | ^12.5.23 |
| Vite | `@voidzero-dev/vite-plus-core` 1.4.0（Vite の代替パッケージ） |

設定の初期値: `DB_CONNECTION=sqlite`、`MAIL_MAILER=log`（メールはログ出力。Step 5 で Resend に切り替える）、`APP_URL=http://localhost:8000`。

Boost により、`.mcp.json`、`AGENTS.md`、`boost.json`、`.claude/`、`.cursor/` が生成された。git リポジトリは未初期化。

## 生成された構成（Step 2 後に実物で確認）

### 認証機能（Fortify）

`config/fortify.php` で有効になっている機能:

| 機能 | 状態 |
|---|---|
| 会員登録（`registration`） | 有効 |
| パスワードリセット（`resetPasswords`） | 有効 |
| メール認証（`emailVerification`） | **有効** |
| 2要素認証（`twoFactorAuthentication`） | 有効 |
| パスキー（`passkeys`） | 有効 |

- `app/Models/User.php` は、すでに `MustVerifyEmail` を実装している。
  - 他に `PasskeyUser`、`TwoFactorAuthenticatable`、`PasskeyAuthenticatable` も使っている。
- `routes/web.php` では、`dashboard` に `auth` と `verified` のミドルウェアが付いている。
- `app/Actions/Fortify/` には `CreateNewUser.php` と `ResetUserPassword.php` がある。
- **Step 4 で追加実装は不要。** 実際にメール認証が動くことの確認が中心になる。
- Devise との対応:

| Devise | このキット |
|---|---|
| `:registerable` | `Features::registration()` |
| `:recoverable` | `Features::resetPasswords()` |
| `:confirmable` | `Features::emailVerification()` + `MustVerifyEmail` |
| `:rememberable` | ログイン画面の「ログイン状態を保持」（未確認。Step 3 で確認する） |
| `:lockable` | ログイン試行の制限（スロットリング） |
| `:trackable` | なし（必要なら自前実装） |

### ルート

| 種別 | パス |
|---|---|
| 公開 | `/`（welcome） |
| 要ログイン + メール認証済み | `/dashboard` |
| 設定 | `/settings/profile`、`/settings/security`、`/settings/appearance`、`PUT /settings/password` |
| パスキー | `/.well-known/passkey-endpoints` |

- 登録、ログイン、リセットなどの認証ルートは Fortify が登録する。
- `php artisan route:list --except-vendor` には出ないため、Step 3 で `route:list` の全体表示から確認する。

### 画面（`resources/js/pages/`）

- `auth/`: login、register、forgot-password、reset-password、verify-email、confirm-password、two-factor-challenge
- `settings/`: profile、security、appearance
- その他: `welcome.tsx`、`dashboard.tsx`

### Boost の生成物

- `boost.json` の設定:
  - 対象エージェントは `claude_code` と `cursor`
  - ガイドラインと MCP は有効
  - Sail と Nightwatch は無効
- 導入されたスキル: fortify-development、inertia-react-development、wayfinder-development、laravel-best-practices、testing-best-practices、tailwindcss-development、deploying-to-cloud、infer-conventions
- MCP サーバー: `.mcp.json` に `laravel-boost`（`php artisan boost:mcp`）を登録
- `.gitignore` に `/.claude`、`/.cursor`、`/.mcp.json`、`/AGENTS.md`、`/boost.json` が入っているため、git 管理には含まれない。

## Step 3・4 の確認記録

`composer run dev` で起動（ポート 8000）し、Chrome で確認した。

| 確認項目 | 結果 |
|---|---|
| `/`（トップ）の表示 | OK。コンソールエラーなし |
| `/login` の表示 | OK。「Remember me」、パスワードリセットのリンク、パスキーでのログインがある |
| `/register` の表示 | OK |
| テストユーザーの登録（`test@example.com`） | OK。`/email/verify` に遷移し、DB の `email_verified_at` は空 |
| 認証メールの出力 | OK。件名は「Verify your email address」。送信元は `hello@example.com`（`MAIL_MAILER=log` のため `storage/logs/laravel.log` に出力） |
| メール内の認証リンクを開く | OK。`/dashboard` に遷移し、`email_verified_at` が入った |

- 認証リンクは署名付き URL（`?expires=...&signature=...`）で、有効期限がある。
- 送信元が `hello@example.com` のままになっている。Step 5 で、Resend の検証済みドメインのアドレスに変更する（`MAIL_FROM_ADDRESS`）。
- テストユーザーはローカルの SQLite にだけ存在する。本番には影響しない。

## Step 5 の記録（Resend の導入）

- `composer require resend/resend-php` で SDK（`^1.19`）を導入した。
- `.env` の設定:

| 変数 | 値 | 備考 |
|---|---|---|
| `MAIL_MAILER` | `resend` | `log` から変更 |
| `MAIL_FROM_ADDRESS` | `onboarding@resend.dev` | `hello@example.com` から変更。独自ドメインを検証したら、そのドメインのアドレスに変える |
| `RESEND_API_KEY` | （非公開） | `config/services.php` の `resend.key` が読む。値はこのファイルにもチャットにも書かない |

- 別プロジェクトの設定が混ざっていたため、`RESEND_FROM_EMAIL` と `EMAIL_VERIFICATION_TTL_MIN` を削除した。Laravel では使われない変数。
- メール認証リンクの有効期限は Laravel のデフォルト（60分）。`.env` では変えられない。`config/auth.php` に `verification.expire` のキーはないため、変更する場合は追加する。なお、`config/auth.php` にある `passwords.users.expire`（60）は、パスワードリセット用の設定。
- 設定の確認結果: `mail.default=resend`、`mail.from.address=onboarding@resend.dev`、`services.resend.key` は設定済み。
- 制約: 送信元が `onboarding@resend.dev` の間は、Resend に登録したアカウントのメールアドレス宛てにしか送れない。
- **注意: `.env` を変更したら、開発サーバー（`composer run dev`）を再起動する。**
  - 起動中のサーバーは、起動時の `.env` の値を使い続ける。
  - 今回は `.env` を変更する前から起動していたサーバーが `MAIL_MAILER=log` のままで、最初の認証メールが Resend ではなく `storage/logs/laravel.log` に出力された。
  - 見分け方: `laravel.log` に `From: Laravel <hello@example.com>` のようなメールが出ていたら、Resend には送られていない。
  - 再起動後に認証メールを再送したところ、メールが届いた。

## 運用ルール

- git の commit / push / ブランチ作成は、明示の指示があるまで行わない。
- 進捗はこのファイルの表を更新して残す。

## 参考

- [Starter Kits](https://laravel.com/framework/docs/starter-kits)
- [Email Verification](https://laravel.com/framework/docs/verification)
- [Release Notes](https://laravel.com/framework/docs/releases)
- [Mail](https://laravel.com/framework/docs/mail)
- [Send emails with Laravel - Resend](https://resend.com/docs/send-with-laravel)
