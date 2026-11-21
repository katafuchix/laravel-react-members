<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\LowercasesEmail;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

class RegisterRequest extends FormRequest
{
    use LowercasesEmail, PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 会員登録（Web）と同じ検証ルールに、端末名を加える。
     *
     * @return array<string, array<int, Password|Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }
}
