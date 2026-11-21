<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\SendEmailVerificationCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    /**
     * 会員を登録し、その端末用のトークンを返す。メール認証のコードも送る。
     * Registered イベントは発行しない（標準の認証メールはリンクだけで、アプリでは使えないため）。
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request, SendEmailVerificationCode $sendEmailVerificationCode): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        $sendEmailVerificationCode->handle($user);

        return $this->tokenResponse($user, $request->string('device_name')->toString(), 201);
    }

    /**
     * ログインして、その端末用のトークンを返す。
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();

        // 2要素認証を有効にした会員は、API では2要素認証を回避できてしまうため、
        // Web でログインしてもらう（API 対応は次のフェーズ）。
        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'message' => '2要素認証を有効にしているアカウントは、アプリではログインできません。',
            ], 403);
        }

        return $this->tokenResponse($user, $request->string('device_name')->toString());
    }

    /**
     * 今のトークンだけを削除する（ほかの端末のトークンは残る）。
     */
    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function tokenResponse(User $user, string $deviceName, int $status = 200): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => new UserResource($user),
        ], $status);
    }
}
