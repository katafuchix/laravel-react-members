<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ローカル開発用のテストユーザー。
 *
 * DatabaseSeeder からは呼んでいない。必要なときだけ手動で実行する:
 *   php artisan db:seed --class=LocalTestUserSeeder
 *
 * 本番では実行しないこと。
 */
class LocalTestUserSeeder extends Seeder
{
    public function run(): void
    {
        // Step 3 で登録した、メール未送信用のテストユーザー（認証済み）
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('Lrv-Members-7Kq2xW9z'),
                'email_verified_at' => now(),
            ],
        );

        // Step 6 で登録した、Resend 経由の認証メール確認用ユーザー（認証済み）
        User::updateOrCreate(
            ['email' => 'katafuchix@gmail.com'],
            [
                'name' => 'Resend Test',
                'password' => Hash::make('Lrv-Members-7Kq2xW9z'),
                'email_verified_at' => now(),
            ],
        );
    }
}
