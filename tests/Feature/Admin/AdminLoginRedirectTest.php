<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_returns_to_the_admin_page_they_tried_to_open(): void
    {
        $admin = Admin::factory()->create();

        $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.users.index'));
    }

    public function test_admin_login_ignores_a_stale_member_side_return_url(): void
    {
        $admin = Admin::factory()->create();

        // 会員側の画面を開いて未ログインで弾かれると、戻り先として /dashboard がセッションに残る。
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }
}
