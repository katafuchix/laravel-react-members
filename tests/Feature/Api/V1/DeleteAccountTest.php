<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_the_account_with_the_correct_password(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->deleteJson(route('api.v1.me.destroy'), ['password' => 'password'])
            ->assertNoContent();

        $this->assertModelMissing($user);
    }

    public function test_all_tokens_of_the_user_are_deleted(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone')->plainTextToken;
        $user->createToken('iPad');

        $this->withToken($token)
            ->deleteJson(route('api.v1.me.destroy'), ['password' => 'password'])
            ->assertNoContent();

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_tokens_of_other_users_are_kept(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $other->createToken('iPhone');

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->deleteJson(route('api.v1.me.destroy'), ['password' => 'password'])
            ->assertNoContent();

        $this->assertModelExists($other);
        $this->assertCount(1, $other->tokens);
    }

    public function test_deleted_user_cannot_use_the_token_anymore(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone')->plainTextToken;

        $this->withToken($token)
            ->deleteJson(route('api.v1.me.destroy'), ['password' => 'password'])
            ->assertNoContent();

        // 認証のキャッシュを捨てて、別のリクエストとして再度送る。
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_deleted_user_cannot_login_again(): void
    {
        $user = User::factory()->create();
        $email = $user->email;

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->deleteJson(route('api.v1.me.destroy'), ['password' => 'password'])
            ->assertNoContent();

        $this->postJson(route('api.v1.login'), [
            'email' => $email,
            'password' => 'password',
            'device_name' => 'iPhone',
        ])->assertUnprocessable();
    }

    public function test_wrong_password_does_not_delete_anything(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->deleteJson(route('api.v1.me.destroy'), ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertModelExists($user);
        $this->assertCount(1, $user->tokens);
    }

    public function test_password_is_required(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->deleteJson(route('api.v1.me.destroy'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertModelExists($user);
    }

    public function test_it_requires_authentication(): void
    {
        $this->deleteJson(route('api.v1.me.destroy'), ['password' => 'password'])->assertUnauthorized();
    }
}
