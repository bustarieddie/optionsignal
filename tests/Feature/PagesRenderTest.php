<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DefaultStrategySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\WatchlistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, DefaultStrategySeeder::class]);

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('Admin');
        $this->admin->riskSetting()->create(config('risk.defaults'));
        $this->admin->watchlists()->create(['ticker' => 'NVDA', 'optionable' => true, 'active' => true]);
    }

    public static function pages(): array
    {
        return [
            ['/dashboard'], ['/signals'], ['/watchlist'], ['/watchlist/create'],
            ['/strategies'], ['/strategies/create'], ['/trades'], ['/trades/create'],
            ['/backtests'], ['/backtests/create'], ['/risk'], ['/pine-script'],
            ['/account/profile'], ['/account/api-tokens'],
            ['/admin/users'], ['/admin/webhooks'], ['/admin/mcp-audit'],
            ['/admin/performance'], ['/admin/risk-defaults'],
        ];
    }

    /**
     * @dataProvider pages
     */
    public function test_page_renders_for_admin(string $url): void
    {
        $this->actingAs($this->admin)->get($url)->assertOk();
    }
}
