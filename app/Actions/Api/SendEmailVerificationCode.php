<?php

namespace App\Actions\Api;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\Api\VerificationCodeNotification;
use Illuminate\Support\Facades\URL;

class SendEmailVerificationCode
{
    /**
     * 6桁のコードと認証リンクを発行して、会員の今のメールアドレスに送る。
     * すでにあるコードは上書きされ、古いコードは使えなくなる（失敗の回数もリセットされる）。
     */
    public function handle(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $record = EmailVerificationCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'email' => $user->email,
                'code' => $code,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(EmailVerificationCode::EXPIRES_IN_MINUTES),
            ],
        );

        $user->notify(new VerificationCodeNotification($code, $this->verificationUrl($record)));
    }

    /**
     * メールに載せる、署名付きの認証リンク。
     * コードの印（k）を含めるので、コードを再送したり使ったりすると、古いリンクは使えなくなる。
     */
    private function verificationUrl(EmailVerificationCode $record): string
    {
        return URL::temporarySignedRoute(
            'api.v1.email.verify-link',
            $record->expires_at,
            ['id' => $record->user_id, 'k' => $record->linkKey()],
        );
    }
}
