<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\SendEmailVerificationCode;
use App\Actions\Api\VerifyEmailWithCode;
use App\Actions\Api\VerifyEmailWithLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VerifyEmailRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailVerificationController extends Controller
{
    /**
     * メールで届いた6桁のコードで、メールアドレスを認証する。
     * すでに認証済みなら、何もせず今の会員の情報を返す。
     */
    public function verify(VerifyEmailRequest $request, VerifyEmailWithCode $verifyEmailWithCode): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $verifyEmailWithCode->handle($user, $request->string('code')->toString());
        }

        return new UserResource($user->refresh());
    }

    /**
     * メールの認証リンク（署名付き）を開いたときの処理。ログインは不要で、結果のページを返す。
     *
     * @unauthenticated
     */
    public function verifyLink(Request $request, int $id, VerifyEmailWithLink $verifyEmailWithLink): Response
    {
        $verified = $verifyEmailWithLink->handle($id, $request->string('k')->toString());

        return $verified
            ? response()->view('email-verification-result', [
                'title' => 'メールアドレスを認証しました',
                'message' => 'アプリに戻ってください。',
            ])
            : response()->view('email-verification-result', [
                'title' => 'このリンクは使えません',
                'message' => '期限が切れているか、すでに使われています。アプリから確認コードを再送してください。',
            ], 422);
    }

    /**
     * 新しいコードを送る（古いコードは使えなくなる）。認証済みなら何もしない。
     */
    public function resend(Request $request, SendEmailVerificationCode $sendEmailVerificationCode): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $sendEmailVerificationCode->handle($user);
        }

        return response()->noContent();
    }
}
