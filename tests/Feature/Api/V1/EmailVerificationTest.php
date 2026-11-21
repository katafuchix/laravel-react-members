<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Api\SendEmailVerificationCode;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\Api\VerificationCodeNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 確認コード '123456' を持つ、未認証の会員と、そのトークンを用意する。
     *
     * @return array{0: User, 1: string, 2: EmailVerificationCode}
     */
    private function unverifiedUserWithCode(?EmailVerificationCode $record = null): array
    {
        $record ??= EmailVerificationCode::factory()->create();
        $user = $record->user;

        return [$user, $user->createToken('iPhone')->plainTextToken, $record];
    }

    public function test_register_sends_a_verification_code_notification(): void
    {
        Notification::fake();

        $this->postJson(route('api.v1.register'), [
            'name' => 'Test User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'iPhone',
        ])->assertCreated();

        $user = User::where('email', 'new-user@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) {
            return preg_match('/^\d{6}$/', $notification->code) === 1;
        });
        // 標準の認証メール（リンクだけ）は送らない。
        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_the_code_is_stored_hashed_and_bound_to_the_email(): void
    {
        Notification::fake();

        $this->postJson(route('api.v1.register'), [
            'name' => 'Test User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'iPhone',
        ])->assertCreated();

        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $record = EmailVerificationCode::where('user_id', $user->id)->firstOrFail();

        $sentCode = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$sentCode) {
            $sentCode = $notification->code;

            return true;
        });

        $this->assertNotSame($sentCode, $record->code);
        $this->assertTrue(Hash::check($sentCode, $record->code));
        $this->assertSame('new-user@example.com', $record->email);
        $this->assertTrue($record->expires_at->isFuture());
    }

    public function test_notification_mail_contains_the_code(): void
    {
        $user = User::factory()->unverified()->create();

        $mail = (new VerificationCodeNotification('482913', 'https://example.test/verify'))->toMail($user);

        $this->assertStringContainsString('482913', implode("\n", $mail->introLines));
        $this->assertStringContainsString('10分', implode("\n", $mail->introLines));
        $this->assertSame('https://example.test/verify', $mail->actionUrl);
    }

    /**
     * 送られた通知から、認証リンクを取り出す。
     */
    private function sentLink(User $user): string
    {
        $url = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$url) {
            $url = $notification->verificationUrl;

            return true;
        });

        return $url;
    }

    public function test_register_email_contains_a_signed_link_that_verifies_the_email(): void
    {
        Notification::fake();
        Event::fake([Verified::class]);

        $this->postJson(route('api.v1.register'), [
            'name' => 'Test User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'iPhone',
        ])->assertCreated();

        $user = User::where('email', 'new-user@example.com')->firstOrFail();

        // ログインしていないブラウザで開く想定（トークンなし）。
        $this->get($this->sentLink($user))
            ->assertOk()
            ->assertSee('メールアドレスを認証しました');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
        Event::assertDispatched(Verified::class);
    }

    public function test_link_cannot_be_used_twice(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        app(SendEmailVerificationCode::class)->handle($user);
        $link = $this->sentLink($user);

        $this->get($link)->assertOk();

        User::whereKey($user->id)->update(['email_verified_at' => null]);

        $this->get($link)->assertUnprocessable()->assertSee('このリンクは使えません');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_is_invalid_after_the_code_is_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $action = app(SendEmailVerificationCode::class);
        $action->handle($user);
        $oldLink = $this->sentLink($user);

        $action->handle($user);

        $this->get($oldLink)->assertUnprocessable();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_is_invalid_after_the_email_is_changed(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        app(SendEmailVerificationCode::class)->handle($user);
        $link = $this->sentLink($user);

        $user->forceFill(['email' => 'changed@example.com'])->save();

        $this->get($link)->assertUnprocessable();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_link_is_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        app(SendEmailVerificationCode::class)->handle($user);
        $link = $this->sentLink($user);

        $this->travel(EmailVerificationCode::EXPIRES_IN_MINUTES + 1)->minutes();

        // 署名の有効期限が切れているので、署名の検証で拒否される。
        $this->get($link)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_link_is_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        app(SendEmailVerificationCode::class)->handle($user);
        $link = $this->sentLink($user);

        // 他の会員の ID に書き換えても、署名が合わない。
        $this->get(str_replace('/verify/'.$user->id.'?', '/verify/'.$other->id.'?', $link))->assertForbidden();
        // 印（k）を書き換えても、署名が合わない。
        $this->get(preg_replace('/k=[0-9a-f]+/', 'k='.str_repeat('0', 64), $link))->assertForbidden();
        // 署名がなければ開けない。
        $this->get(route('api.v1.email.verify-link', ['id' => $user->id, 'k' => 'x']))->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_link_for_an_already_verified_user_shows_success(): void
    {
        $user = User::factory()->create();

        $link = URL::temporarySignedRoute('api.v1.email.verify-link', now()->addMinutes(10), ['id' => $user->id, 'k' => 'x']);

        $this->get($link)->assertOk()->assertSee('メールアドレスを認証しました');
    }

    public function test_link_for_a_deleted_user_is_rejected(): void
    {
        $link = URL::temporarySignedRoute('api.v1.email.verify-link', now()->addMinutes(10), ['id' => 99999, 'k' => 'x']);

        $this->get($link)->assertUnprocessable();
    }

    public function test_resend_email_contains_a_new_link(): void
    {
        Notification::fake();
        [$user, $token] = $this->unverifiedUserWithCode();

        $this->withToken($token)->postJson(route('api.v1.email.resend'))->assertNoContent();

        $this->get($this->sentLink($user))->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_correct_code_verifies_the_email(): void
    {
        Event::fake([Verified::class]);
        [$user, $token] = $this->unverifiedUserWithCode();

        $this->withToken($token)
            ->postJson(route('api.v1.email.verify'), ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email_verified_at', fn ($value) => $value !== null);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
        Event::assertDispatched(Verified::class);
    }

    public function test_used_code_cannot_be_used_again(): void
    {
        [$user, $token] = $this->unverifiedUserWithCode();

        $this->withToken($token)->postJson(route('api.v1.email.verify'), ['code' => '123456'])->assertOk();

        // 認証したあとで再び未認証に戻っても、使ったコードは残っていないので、もう一度は使えない。
        User::whereKey($user->id)->update(['email_verified_at' => null]);
        // 認証のキャッシュを捨てて、別のリクエストとして再度送る。
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->postJson(route('api.v1.email.verify'), ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_wrong_code_is_rejected_and_counts_as_an_attempt(): void
    {
        [$user, $token, $record] = $this->unverifiedUserWithCode();

        $this->withToken($token)
            ->postJson(route('api.v1.email.verify'), ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertSame(1, $record->fresh()->attempts);
    }

    public function test_code_is_locked_after_too_many_wrong_attempts(): void
    {
        [$user, $token] = $this->unverifiedUserWithCode(
            EmailVerificationCode::factory()->withAttempts(EmailVerificationCode::MAX_ATTEMPTS)->create(),
        );

        // 上限に達したあとは、正しいコードでも拒否される。
        $this->withToken($token)
            ->postJson(route('api.v1.email.verify'), ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_five_wrong_attempts_lock_the_code(): void
    {
        [$user, $token] = $this->unverifiedUserWithCode();

        for ($i = 0; $i < EmailVerificationCode::MAX_ATTEMPTS; $i++) {
            $this->withToken($token)->postJson(route('api.v1.email.verify'), ['code' => '000000'])->assertUnprocessable();
        }

        $this->withToken($token)
            ->postJson(route('api.v1.email.verify'), ['code' => '123456'])
            ->assertUnprocessable();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_code_is_rejected(): void
    {
        [$user, $token] = $this->unverifiedUserWithCode(EmailVerificationCode::factory()->expired()->create());

        $this->withToken($token)
            ->postJson(route('api.v1.email.verify'), ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_code_issued_for_the_old_email_cannot_verify_the_new_email(): void
    {
        [$user, $token] = $this->unverifiedUserWithCode();

        $user->forceFill(['email' => 'changed@example.com'])->save();

        $this->withToken($token)
            ->postJson(route('api.v1.email.verify'), ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_user_without_a_code_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->postJson(route('api.v1.email.verify'), ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_code_must_be_six_digits(): void
    {
        [, $token] = $this->unverifiedUserWithCode();

        foreach ([null, '', '12345', '1234567', 'abcdef'] as $code) {
            $this->withToken($token)
                ->postJson(route('api.v1.email.verify'), ['code' => $code])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('code');
        }
    }

    public function test_already_verified_user_gets_the_current_user_back(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->postJson(route('api.v1.email.verify'), ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_verify_requires_authentication(): void
    {
        $this->postJson(route('api.v1.email.verify'), ['code' => '123456'])->assertUnauthorized();
    }

    public function test_codes_of_other_users_are_not_usable(): void
    {
        EmailVerificationCode::factory()->create();
        $other = User::factory()->unverified()->create();

        $this->withToken($other->createToken('iPhone')->plainTextToken)
            ->postJson(route('api.v1.email.verify'), ['code' => '123456'])
            ->assertUnprocessable();

        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_resend_sends_a_new_code_and_invalidates_the_old_one(): void
    {
        Notification::fake();
        [$user, $token, $record] = $this->unverifiedUserWithCode(
            EmailVerificationCode::factory()->withAttempts(3)->create(),
        );
        $oldHash = $record->code;

        $this->withToken($token)->postJson(route('api.v1.email.resend'))->assertNoContent();

        Notification::assertSentToTimes($user, VerificationCodeNotification::class, 1);

        $newRecord = EmailVerificationCode::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, EmailVerificationCode::count());
        $this->assertNotSame($oldHash, $newRecord->code);
        $this->assertSame(0, $newRecord->attempts);
    }

    public function test_resend_does_nothing_for_a_verified_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->postJson(route('api.v1.email.resend'))
            ->assertNoContent();

        Notification::assertNothingSent();
        $this->assertSame(0, EmailVerificationCode::count());
    }

    public function test_resend_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('iPhone')->plainTextToken;

        for ($i = 0; $i < 3; $i++) {
            $this->withToken($token)->postJson(route('api.v1.email.resend'))->assertNoContent();
        }

        $this->withToken($token)->postJson(route('api.v1.email.resend'))->assertTooManyRequests();
    }

    public function test_resend_requires_authentication(): void
    {
        $this->postJson(route('api.v1.email.resend'))->assertUnauthorized();
    }

    public function test_changing_the_email_sends_a_code_to_the_new_address(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->patchJson(route('api.v1.me.update'), ['email' => 'changed@example.com'])
            ->assertOk()
            ->assertJsonPath('email_verified_at', null);

        Notification::assertSentTo($user, VerificationCodeNotification::class);
        $this->assertSame('changed@example.com', EmailVerificationCode::where('user_id', $user->id)->firstOrFail()->email);
    }

    public function test_changing_only_the_name_does_not_send_a_code(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->patchJson(route('api.v1.me.update'), ['name' => 'New Name'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    public function test_deleting_the_account_removes_the_code(): void
    {
        [$user, $token] = $this->unverifiedUserWithCode();

        $this->withToken($token)
            ->deleteJson(route('api.v1.me.destroy'), ['password' => 'password'])
            ->assertNoContent();

        $this->assertSame(0, EmailVerificationCode::count());
    }
}
