<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DefaultStrategySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTokenAbilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, DefaultStrategySeeder::class]);

        // The 'ability'/'abilities' middleware aliases used by routes/api.php
        // are provided by Sanctum but are not registered as aliases in this
        // app's bootstrap. Register them here (test-only) so the ability gate
        // on POST /api/trades resolves and can be exercised.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('ability', CheckAbilities::class);
        $router->aliasMiddleware('abilities', CheckForAnyAbility::class);
    }

    private function trader(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Trader');
        $user->riskSetting()->create(config('risk.defaults'));

        return $user;
    }

    public function test_token_is_persisted_with_limited_abilities(): void
    {
        $user = $this->trader();

        $user->createToken('mcp-read-only', ['mcp:read']);

        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertSame(['mcp:read'], $user->fresh()->tokens->first()->abilities);
    }

    public function test_token_without_write_ability_cannot_create_trade(): void
    {
        $user = $this->trader();

        // Acting with a read-only token: tokenCan('mcp:write') must be false
        // and the trades:write-gated endpoint must be forbidden.
        Sanctum::actingAs($user, ['mcp:read']);

        $this->assertFalse($user->tokenCan('mcp:write'));

        $this->postJson('/api/trades', [
            'ticker' => 'NVDA',
            'direction' => 'call',
        ])->assertForbidden();

        $this->assertDatabaseCount('trades', 0);
    }

    public function test_token_with_write_ability_can_create_trade(): void
    {
        $user = $this->trader();

        Sanctum::actingAs($user, ['trades:write']);

        $this->postJson('/api/trades', [
            'ticker' => 'NVDA',
            'direction' => 'call',
            'entry_price' => 5.25,
            'quantity' => 1,
        ])->assertCreated();

        $this->assertDatabaseHas('trades', [
            'user_id' => $user->id,
            'ticker' => 'NVDA',
            'direction' => 'call',
            'status' => 'open',
        ]);
    }
}
