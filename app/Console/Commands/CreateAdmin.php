<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * 管理者を作成する。公開の登録画面は作らず、このコマンドだけで作る。
 *
 * パスワードはシェルの履歴に残るため、引数では受け取らず、必ず対話で入力する。
 */
#[Signature('admin:create
    {--name= : 管理者の名前（省略すると対話で入力）}
    {--email= : 管理者のメールアドレス（省略すると対話で入力）}')]
#[Description('管理者（Admin）を作成する')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error('パスワードを入力するため、対話モードで実行してください（--no-interaction は使えません）。');

            return self::FAILURE;
        }

        $name = $this->resolveName();
        $email = $this->resolveEmail();

        if ($name === null || $email === null) {
            return self::FAILURE;
        }

        $password = password(
            label: 'Password',
            required: true,
            validate: fn (string $value) => $this->validationError('password', $value, $this->passwordRules()),
        );

        password(
            label: 'Confirm password',
            required: true,
            validate: fn (string $value) => $value === $password ? null : 'パスワードが一致しません。',
        );

        $admin = Admin::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->components->info("管理者を作成しました: {$admin->name} <{$admin->email}>");

        return self::SUCCESS;
    }

    private function resolveName(): ?string
    {
        $option = $this->option('name');

        if ($option !== null) {
            return $this->validateOption('name', $option, $this->nameRules());
        }

        return text(
            label: 'Name',
            required: true,
            validate: fn (string $value) => $this->validationError('name', $value, $this->nameRules()),
        );
    }

    private function resolveEmail(): ?string
    {
        $option = $this->option('email');

        if ($option !== null) {
            return $this->validateOption('email', $option, $this->emailRules());
        }

        return text(
            label: 'Email',
            required: true,
            validate: fn (string $value) => $this->validationError('email', $value, $this->emailRules()),
        );
    }

    /**
     * オプションで渡された値を検証し、エラーなら表示して null を返す。
     *
     * @param  array<int, mixed>  $rules
     */
    private function validateOption(string $field, string $value, array $rules): ?string
    {
        $error = $this->validationError($field, $value, $rules);

        if ($error !== null) {
            $this->components->error($error);

            return null;
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function validationError(string $field, string $value, array $rules): ?string
    {
        $validator = Validator::make([$field => $value], [$field => $rules]);

        return $validator->fails() ? $validator->errors()->first($field) : null;
    }

    /**
     * @return array<int, string>
     */
    private function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, mixed>
     */
    private function emailRules(): array
    {
        return ['required', 'string', 'email', 'max:255', Rule::unique(Admin::class)];
    }

    /**
     * 会員側（PasswordValidationRules）と同じ強度のルール。
     *
     * @return array<int, mixed>
     */
    private function passwordRules(): array
    {
        return ['required', 'string', Password::default()];
    }
}
