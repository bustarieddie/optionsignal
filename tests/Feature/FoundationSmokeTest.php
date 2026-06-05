<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DefaultStrategySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, DefaultStrategySeeder::class]);
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in', false);
    }

    public function test_register_page_renders(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_verified_trader_sees_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Trader');
        $user->riskSetting()->create(config('risk.defaults'));

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Win Rate', false)
            ->assertSee('Decision support only', false);
    }

    public function test_viewer_cannot_reach_watchlist_write_routes(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Viewer');

        $this->actingAs($user)
            ->get('/watchlist')
            ->assertForbidden();
    }
}
