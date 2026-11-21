<?php

namespace App\Models;

use Database\Factories\EmailVerificationCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * アプリ（API）向けのメール認証コード。
 *
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property string $code
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'email', 'code', 'attempts', 'expires_at'])]
#[Hidden(['code'])]
class EmailVerificationCode extends Model
{
    /** @use HasFactory<EmailVerificationCodeFactory> */
    use HasFactory;

    /** コードの有効期限（分）。 */
    public const EXPIRES_IN_MINUTES = 10;

    /** 1つのコードに対して、間違えられる回数。 */
    public const MAX_ATTEMPTS = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => 'hashed',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * 認証リンクに埋め込む印。コードを作り直すとハッシュが変わるため、古いリンクは使えなくなる。
     */
    public function linkKey(): string
    {
        return hash('sha256', $this->code.'|'.$this->email);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
