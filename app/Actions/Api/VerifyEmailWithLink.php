<?php

namespace App\Actions\Api;

use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

class VerifyEmailWithLink
{
    /**
     * 認証リンクを検証して、正しければメールアドレスを認証済みにする。
     * 認証できた（またはすでに認証済みだった）ときは true、リンクが使えないときは false を返す。
     */
    public function handle(int $userId, string $key): bool
    {
        $user = User::find($userId);

        if (! $user) {
            return false;
        }

        if ($user->hasVerifiedEmail()) {
            return true;
        }

        $verified = DB::transaction(function () use ($user, $key): bool {
            $record = EmailVerificationCode::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            // コードが無い（使用済み）、期限切れ、メールを変更した、コードを再発行した、のいずれかなら使えない。
            if (! $record
                || $record->expires_at->isPast()
                || $record->email !== $user->email
                || ! hash_equals($record->linkKey(), $key)) {
                return false;
            }

            $record->delete();
            $user->markEmailAsVerified();

            return true;
        });

        if ($verified) {
            event(new Verified($user));
        }

        return $verified;
    }
}
