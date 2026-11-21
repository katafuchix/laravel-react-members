<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_admin_login(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_logged_in_members_cannot_see_the_admin_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_admins_can_see_the_users_list(): void
    {
        User::factory()->count(3)->create();

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->where('users.total', 3)
                ->has('users.data', 3));
    }

    public function test_the_users_list_does_not_expose_sensitive_columns(): void
    {
        User::factory()->create();

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data.0', fn (Assert $row) => $row
                    ->hasAll(['id', 'name', 'email', 'email_verified_at', 'created_at'])
                    ->missing('password')
                    ->missing('remember_token')
                    ->missing('two_factor_secret')));
    }

    public function test_the_users_list_is_paginated_with_newest_first(): void
    {
        User::factory()->count(16)->create();

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 15)
                ->where('users.last_page', 2)
                ->where('users.data.0.id', User::max('id')));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.current_page', 2));
    }

    public function test_the_dashboard_shows_user_counts(): void
    {
        User::factory()->count(2)->create();
        User::factory()->unverified()->count(3)->create();
        User::factory()->create(['created_at' => now()->subDays(30)]);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dashboard')
                ->where('stats.total', 6)
                ->where('stats.verified', 3)
                ->where('stats.unverified', 3)
                ->where('stats.registeredLast7Days', 5));
    }
}
