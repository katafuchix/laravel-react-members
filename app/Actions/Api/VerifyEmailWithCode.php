<?php

namespace App\Actions\Api;

use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VerifyEmailWithCode
{
    private const RESULT_VERIFIED = 'verified';

    private const RESULT_INVALID = 'invalid';

    private const RESULT_LOCKED = 'locked';

    private const RESULT_WRONG = 'wrong';

    /**
     * コードを検証して、正しければメールアドレスを認証済みにする。
     *
     * 失敗の回数を記録するため、判定はトランザクションの中で行い、
     * 例外はトランザクションの外で投げる（中で投げると、回数の記録も巻き戻ってしまう）。
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $code): void
    {
        $result = DB::transaction(function () use ($user, $code): string {
            $record = EmailVerificationCode::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            // コードが無い、期限切れ、コードを発行したあとにメールを変更した、のいずれか。
            if (! $record || $record->expires_at->isPast() || $record->email !== $user->email) {
                return self::RESULT_INVALID;
            }

            if ($record->attempts >= EmailVerificationCode::MAX_ATTEMPTS) {
                return self::RESULT_LOCKED;
            }

            if (! Hash::check($code, $record->code)) {
                $record->increment('attempts');

                return self::RESULT_WRONG;
            }

            $record->delete();
            $user->markEmailAsVerified();

            return self::RESULT_VERIFIED;
        });

        match ($result) {
            self::RESULT_VERIFIED => event(new Verified($user)),
            self::RESULT_INVALID => throw ValidationException::withMessages([
                'code' => '確認コードが無効か、期限が切れています。新しいコードを再送してください。',
            ]),
            self::RESULT_LOCKED => throw ValidationException::withMessages([
                'code' => '間違いが多すぎます。新しいコードを再送してください。',
            ]),
            default => throw ValidationException::withMessages([
                'code' => '確認コードが正しくありません。',
            ]),
        };
    }
}
