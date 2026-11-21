<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_the_current_user_without_sensitive_fields(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at->toIso8601String(),
            ]);
    }

    public function test_show_requires_authentication(): void
    {
        $this->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_user_can_update_name_only(): void
    {
        $user = User::factory()->create(['email' => 'keep@example.com']);

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->patchJson(route('api.v1.me.update'), ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('email', 'keep@example.com');

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_changing_the_email_resets_verification(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->patchJson(route('api.v1.me.update'), ['email' => 'changed@example.com'])
            ->assertOk()
            ->assertJsonPath('email', 'changed@example.com')
            ->assertJsonPath('email_verified_at', null);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_keeping_the_same_email_keeps_verification(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->patchJson(route('api.v1.me.update'), ['name' => 'Same', 'email' => $user->email])
            ->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_is_lowercased(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->patchJson(route('api.v1.me.update'), ['email' => 'Mixed.Case@Example.com'])
            ->assertOk()
            ->assertJsonPath('email', 'mixed.case@example.com');
    }

    public function test_email_used_by_another_user_is_rejected(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->patchJson(route('api.v1.me.update'), ['email' => $other->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_invalid_values_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('iPhone')->plainTextToken)
            ->patchJson(route('api.v1.me.update'), ['name' => '', 'email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_update_requires_authentication(): void
    {
        $this->patchJson(route('api.v1.me.update'), ['name' => 'x'])->assertUnauthorized();
    }
}
