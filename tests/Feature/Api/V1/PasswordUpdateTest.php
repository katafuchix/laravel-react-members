<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];
    }

    public function test_user_can_change_the_password(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->putJson(route('api.v1.me.password'), $this->validPayload())
            ->assertNoContent();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_other_devices_are_logged_out_but_the_current_one_stays(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('iPhone')->plainTextToken;
        $user->createToken('iPad');

        $this->withToken($current)
            ->putJson(route('api.v1.me.password'), $this->validPayload())
            ->assertNoContent();

        $tokens = $user->fresh()->tokens;
        $this->assertCount(1, $tokens);
        $this->assertSame('iPhone', $tokens->first()->name);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->putJson(route('api.v1.me.password'), [
                ...$this->validPayload(),
                'current_password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->putJson(route('api.v1.me.password'), [
                ...$this->validPayload(),
                'password_confirmation' => 'different',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_required_fields_are_validated(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->putJson(route('api.v1.me.password'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password', 'password']);
    }

    public function test_it_requires_authentication(): void
    {
        $this->putJson(route('api.v1.me.password'), $this->validPayload())->assertUnauthorized();
    }

    public function test_it_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone')->plainTextToken;

        for ($i = 0; $i < 6; $i++) {
            $this->withToken($token)->putJson(route('api.v1.me.password'), [
                ...$this->validPayload(),
                'current_password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->withToken($token)
            ->putJson(route('api.v1.me.password'), $this->validPayload())
            ->assertTooManyRequests();
    }
}
