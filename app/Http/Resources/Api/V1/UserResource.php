<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * `data` で包まない（ログイン、登録レスポンスの `user` と同じ形にそろえる）。
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * パスワードや2要素認証の情報は含めない。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
        ];
    }
}
