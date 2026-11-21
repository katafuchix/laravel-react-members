<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Test User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'iPhone',
        ];
    }

    public function test_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson(route('api.v1.register'), $this->validPayload());

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'email_verified_at']])
            ->assertJsonPath('user.email', 'new-user@example.com')
            ->assertJsonPath('user.email_verified_at', null);

        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $this->assertCount(1, $user->tokens);
        $this->assertSame('iPhone', $user->tokens->first()->name);
    }

    public function test_issued_token_can_be_used_immediately(): void
    {
        $token = $this->postJson(route('api.v1.register'), $this->validPayload())->json('token');

        $this->withToken($token)->getJson(route('api.v1.me'))->assertOk();
    }

    public function test_password_is_stored_hashed_and_not_returned(): void
    {
        $response = $this->postJson(route('api.v1.register'), $this->validPayload());

        $response->assertJsonMissingPath('user.password');

        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_email_is_stored_in_lowercase(): void
    {
        $this->postJson(route('api.v1.register'), [
            ...$this->validPayload(),
            'email' => 'New-User@Example.com',
        ])->assertCreated()->assertJsonPath('user.email', 'new-user@example.com');

        $this->assertDatabaseHas('users', ['email' => 'new-user@example.com']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'new-user@example.com']);

        $this->postJson(route('api.v1.register'), $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_required_fields_are_validated(): void
    {
        $this->postJson(route('api.v1.register'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->postJson(route('api.v1.register'), [
            ...$this->validPayload(),
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
