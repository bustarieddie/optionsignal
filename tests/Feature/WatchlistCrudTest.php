<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DefaultStrategySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchlistCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, DefaultStrategySeeder::class]);
    }

    private function trader(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Trader');
        $user->riskSetting()->create(config('risk.defaults'));

        return $user;
    }

    public function test_trader_can_view_watchlist_index(): void
    {
        $this->actingAs($this->trader())
            ->get('/watchlist')
            ->assertOk();
    }

    public function test_trader_can_store_watchlist_entry(): void
    {
        $user = $this->trader();

        $this->actingAs($user)
            ->post(route('watchlist.store'), [
                'ticker' => 'NVDA',
                'optionable' => '1',
                'active' => '1',
            ])
            ->assertRedirect(route('watchlist.index'));

        $this->assertDatabaseHas('watchlists', [
            'user_id' => $user->id,
            'ticker' => 'NVDA',
            'optionable' => true,
            'active' => true,
        ]);
    }

    public function test_store_requires_ticker(): void
    {
        $this->actingAs($this->trader())
            ->post(route('watchlist.store'), [
                'optionable' => '1',
                'active' => '1',
            ])
            ->assertSessionHasErrors('ticker');

        $this->assertDatabaseCount('watchlists', 0);
    }

    public function test_viewer_cannot_view_or_store_watchlist(): void
    {
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)
            ->get('/watchlist')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('watchlist.store'), [
                'ticker' => 'AAPL',
                'optionable' => '1',
                'active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('watchlists', 0);
    }

    public function test_trader_cannot_edit_another_users_watchlist(): void
    {
        $owner = $this->trader();
        $entry = $owner->watchlists()->create([
            'ticker' => 'TSLA',
            'optionable' => true,
            'active' => true,
        ]);

        $other = $this->trader();

        $this->actingAs($other)
            ->get(route('watchlist.edit', $entry))
            ->assertForbidden();
    }
}
