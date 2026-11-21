<?php

namespace Tests\Feature\Console;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_be_created_interactively(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Name', 'Admin User')
            ->expectsQuestion('Email', 'admin@example.com')
            ->expectsQuestion('Password', 'password')
            ->expectsQuestion('Confirm password', 'password')
            ->assertSuccessful();

        $admin = Admin::where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('Admin User', $admin->name);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_name_and_email_can_be_given_as_options(): void
    {
        $this->artisan('admin:create', ['--name' => 'Option Admin', '--email' => 'option@example.com'])
            ->expectsQuestion('Password', 'password')
            ->expectsQuestion('Confirm password', 'password')
            ->assertSuccessful();

        $this->assertDatabaseHas('admins', ['email' => 'option@example.com', 'name' => 'Option Admin']);
    }

    public function test_duplicate_email_option_is_rejected(): void
    {
        Admin::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('admin:create', ['--name' => 'Another', '--email' => 'taken@example.com'])
            ->assertFailed();

        $this->assertDatabaseCount('admins', 1);
    }

    public function test_invalid_email_option_is_rejected(): void
    {
        $this->artisan('admin:create', ['--name' => 'Invalid', '--email' => 'not-an-email'])
            ->assertFailed();

        $this->assertDatabaseCount('admins', 0);
    }

    public function test_it_refuses_to_run_without_interaction(): void
    {
        $this->artisan('admin:create', ['--name' => 'Admin', '--email' => 'admin@example.com', '--no-interaction' => true])
            ->assertFailed();

        $this->assertDatabaseCount('admins', 0);
    }

    public function test_created_admin_is_stored_with_a_hashed_password(): void
    {
        $this->artisan('admin:create', ['--name' => 'Hashed', '--email' => 'hashed@example.com'])
            ->expectsQuestion('Password', 'plain-text-password')
            ->expectsQuestion('Confirm password', 'plain-text-password')
            ->assertSuccessful();

        $this->assertNotSame('plain-text-password', Admin::firstWhere('email', 'hashed@example.com')->password);
    }
}
