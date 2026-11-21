<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\SendEmailVerificationCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteAccountRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MeController extends Controller
{
    /**
     * 今のログイン中の会員の情報を返す。
     */
    public function show(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user);
    }

    /**
     * 名前、メールを変更する。メールを変えたときは、未認証に戻し、新しいメールにコードを送る。
     */
    public function update(UpdateProfileRequest $request, SendEmailVerificationCode $sendEmailVerificationCode): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $emailChanged = $user->isDirty('email');

        $user->save();

        if ($emailChanged) {
            $sendEmailVerificationCode->handle($user);
        }

        return new UserResource($user);
    }

    /**
     * パスワードを変更する。今の端末以外のトークンは削除し、ほかの端末はログインし直しになる。
     */
    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['password' => $request->string('password')->toString()]);

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->noContent();
    }

    /**
     * 退会する。会員と、すべての端末のトークンを削除する。
     * トークンのテーブルは外部キーの連動削除がないため、明示的に削除する。
     */
    public function destroy(DeleteAccountRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->noContent();
    }
}
