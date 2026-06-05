<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DefaultStrategySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeJournalTest extends TestCase
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

    public function test_trader_can_view_trades_index(): void
    {
        $this->actingAs($this->trader())
            ->get('/trades')
            ->assertOk();
    }

    public function test_trader_can_store_open_trade(): void
    {
        $user = $this->trader();

        $this->actingAs($user)
            ->post(route('trades.store'), [
                'ticker' => 'NVDA',
                'direction' => 'call',
                'quantity' => 1,
                'entry_price' => 5.25,
                'opened_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('trades', [
            'user_id' => $user->id,
            'ticker' => 'NVDA',
            'direction' => 'call',
            'status' => 'open',
        ]);
    }

    public function test_trader_can_add_note_to_own_trade(): void
    {
        $user = $this->trader();
        $trade = $user->trades()->create([
            'ticker' => 'NVDA',
            'direction' => 'call',
            'quantity' => 1,
            'entry_price' => 5.25,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('trades.notes.store', $trade), [
                'body' => 'Held into the close, thesis intact.',
            ])
            ->assertRedirect(route('trades.show', $trade));

        $this->assertDatabaseHas('trade_notes', [
            'trade_id' => $trade->id,
            'user_id' => $user->id,
            'source' => 'user',
            'body' => 'Held into the close, thesis intact.',
        ]);
    }

    public function test_trader_cannot_view_another_users_trade(): void
    {
        $owner = $this->trader();
        $trade = $owner->trades()->create([
            'ticker' => 'TSLA',
            'direction' => 'put',
            'quantity' => 1,
            'entry_price' => 3.10,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $other = $this->trader();

        $this->actingAs($other)
            ->get(route('trades.show', $trade))
            ->assertForbidden();
    }
}
