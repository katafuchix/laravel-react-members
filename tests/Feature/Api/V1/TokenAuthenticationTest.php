<?php

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_gets_a_json_401(): void
    {
        $this->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_user_can_access_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('invalid-token')
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }

    public function test_deleted_token_can_no_longer_be_used(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $user->tokens()->delete();

        $this->withToken($token)
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }

    public function test_admin_session_cannot_access_the_api(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }
}
