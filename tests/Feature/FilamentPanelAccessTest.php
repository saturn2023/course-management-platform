<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may enter the Filament admin panel outside a local environment.
 *
 * Without FilamentUser on the User model, Filament only lets anyone in when
 * APP_ENV is "local", so staging and production returned 403 even for admins.
 * These tests run as production to prove access comes from canAccessPanel().
 */
class FilamentPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'production';
        config(['app.env' => 'production']);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'is_admin' => true,
        ]);
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'Customer',
            'email' => 'customer@example.test',
            'password' => 'secret',
            'is_admin' => false,
        ]);
    }

    public function test_an_admin_can_reach_the_panel_in_production(): void
    {
        $this->assertTrue(app()->environment('production'));

        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    public function test_a_non_admin_is_forbidden_from_the_panel_in_production(): void
    {
        $this->assertTrue(app()->environment('production'));

        $this->actingAs($this->customer())->get('/admin')->assertForbidden();
    }
}
