<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\LowercasesEmail;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Unique;

class UpdateProfileRequest extends FormRequest
{
    use LowercasesEmail, ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 部分更新（名前だけ、メールだけ）ができるよう、各項目は送られたときだけ検証する。
     *
     * @return array<string, array<int, Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['sometimes', ...$this->nameRules()],
            'email' => ['sometimes', ...$this->emailRules($user->id)],
        ];
    }
}
