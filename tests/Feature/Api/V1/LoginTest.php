<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receives_a_token(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'iPhone',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'email_verified_at']])
            ->assertJsonPath('user.id', $user->id);

        $this->assertCount(1, $user->tokens);
    }

    public function test_email_is_case_insensitive_at_login(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->postJson(route('api.v1.login'), [
            'email' => 'Member@Example.com',
            'password' => 'password',
            'device_name' => 'iPhone',
        ])->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_each_login_issues_a_separate_token_per_device(): void
    {
        $user = User::factory()->create();

        foreach (['iPhone', 'iPad'] as $device) {
            $this->postJson(route('api.v1.login'), [
                'email' => $user->email,
                'password' => 'password',
                'device_name' => $device,
            ])->assertOk();
        }

        $this->assertCount(2, $user->tokens);
    }

    public function test_wrong_password_is_rejected_without_a_token(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'iPhone',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertCount(0, $user->tokens);
    }

    public function test_unknown_email_gets_the_same_error_as_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $wrongPassword = $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'iPhone',
        ]);

        $unknownEmail = $this->postJson(route('api.v1.login'), [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
            'device_name' => 'iPhone',
        ]);

        $this->assertSame($wrongPassword->json('errors'), $unknownEmail->json('errors'));
    }

    public function test_required_fields_are_validated(): void
    {
        $this->postJson(route('api.v1.login'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    public function test_user_with_two_factor_enabled_cannot_login_via_api(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'iPhone',
        ])->assertForbidden();

        $this->assertCount(0, $user->tokens);
    }

    public function test_login_is_rate_limited_after_too_many_failed_attempts(): void
    {
        Event::fake([Lockout::class]);

        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.v1.login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
                'device_name' => 'iPhone',
            ]);
        }

        // 上限に達したあとは、正しいパスワードでも拒否される。
        $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'iPhone',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertCount(0, $user->tokens);
        Event::assertDispatched(Lockout::class);
    }
}
