<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('admin.login'))->assertOk();
    }

    public function test_admin_can_login_with_correct_credentials(): void
    {
        $admin = Admin::factory()->create();

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_cannot_login_with_wrong_password(): void
    {
        $admin = Admin::factory()->create();

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_member_credentials_cannot_login_as_admin(): void
    {
        $user = User::factory()->create();

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        $this->assertGuest('web');
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->post(route('admin.login.store'), [])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_remember_option_sets_the_remember_token(): void
    {
        $admin = Admin::factory()->create(['remember_token' => null]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertNotNull($admin->fresh()->remember_token);
    }

    public function test_logged_in_admin_is_redirected_away_from_the_login_screen(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_can_logout(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_admin_logout_keeps_the_member_session(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->actingAs($admin, 'admin')
            ->post(route('admin.logout'));

        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_is_rate_limited_after_too_many_failed_attempts(): void
    {
        Event::fake([Lockout::class]);

        $admin = Admin::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.store'), [
                'email' => $admin->email,
                'password' => 'wrong-password',
            ]);
        }

        // 上限に達したあとは、正しいパスワードでも拒否される。
        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        Event::assertDispatched(Lockout::class);
    }
}
