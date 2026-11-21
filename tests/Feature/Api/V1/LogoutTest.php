<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_deletes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $iphoneToken = $user->createToken('iPhone')->plainTextToken;
        $user->createToken('iPad');

        $this->withToken($iphoneToken)
            ->postJson(route('api.v1.logout'))
            ->assertNoContent();

        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertSame('iPad', $user->fresh()->tokens->first()->name);
    }

    public function test_token_cannot_be_used_after_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone')->plainTextToken;

        $this->withToken($token)->postJson(route('api.v1.logout'))->assertNoContent();

        // 認証のキャッシュを捨てて、別のリクエストとして再度送る。
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson(route('api.v1.logout'))->assertUnauthorized();
    }
}
