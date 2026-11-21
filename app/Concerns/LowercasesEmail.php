<?php

namespace App\Concerns;

use Illuminate\Support\Str;

/**
 * Fortify の `lowercase_usernames` の設定に合わせて、メールアドレスを小文字にそろえる。
 * Web と API で保存形式が食い違わないようにするため。
 */
trait LowercasesEmail
{
    protected function prepareForValidation(): void
    {
        if (config('fortify.lowercase_usernames') && is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }
}
