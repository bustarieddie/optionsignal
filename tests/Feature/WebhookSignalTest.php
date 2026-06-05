<?php

namespace Tests\Feature;

use App\Models\Signal;
use App\Models\TradingViewWebhook;
use App\Models\User;
use Database\Seeders\DefaultStrategySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSignalTest extends TestCase
{
    use RefreshDatabase;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, DefaultStrategySeeder::class]);
        config(['services.tradingview.webhook_secret' => 'test-secret']);

        // A trader watching NVDA so the signal fans out to them.
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Trader');
        $user->watchlists()->create([
            'ticker' => 'NVDA', 'company' => 'NVIDIA', 'optionable' => true, 'active' => true,
        ]);

        $this->payload = [
            'secret' => 'test-secret',
            'ticker' => 'NVDA',
            'timeframe' => '5m',
            'signal' => 'buy_call',
            'price' => 120.50,
            'strategy' => 'EMA_9_21_RSI',
            'ema9' => 121.20,
            'ema21' => 120.80,
            'rsi' => 58,
            'rsi_ma' => 52,
            'vwap' => 119.90,
            'volume_status' => 'above_average',
            'htf_trend' => 'bullish',
            'sr_clear' => true,
            'atr' => 1.5,
            'timestamp' => '2026-06-05T09:35:00-04:00',
        ];
    }

    public function test_rejects_invalid_secret(): void
    {
        $this->postJson('/api/webhooks/tradingview', array_merge($this->payload, ['secret' => 'wrong']))
            ->assertStatus(401);
    }

    public function test_rejects_unknown_symbol(): void
    {
        $this->postJson('/api/webhooks/tradingview', array_merge($this->payload, ['ticker' => 'FAKE']))
            ->assertStatus(422);
    }

    public function test_valid_webhook_creates_graded_signal(): void
    {
        $this->postJson('/api/webhooks/tradingview', $this->payload)
            ->assertStatus(202)
            ->assertJsonPath('status', 'accepted');

        $this->assertDatabaseHas('tradingview_webhooks', ['ticker' => 'NVDA', 'status' => 'processed']);

        $signal = Signal::first();
        $this->assertNotNull($signal);
        $this->assertSame('buy_call', $signal->signal_type);
        $this->assertSame(100, $signal->total_score);
        $this->assertSame('A+', $signal->grade);
        $this->assertSame(6, $signal->scores()->count());
        $this->assertNotNull($signal->optionSuggestion);
        $this->assertSame('call', $signal->optionSuggestion->contract_type);

        // ATR-based levels: entry 120.50, atr 1.5 → SL 119.00, TP 122.00/123.25/123.50
        $this->assertEqualsWithDelta(119.00, (float) $signal->stop_loss, 0.001);
        $this->assertEqualsWithDelta(122.00, (float) $signal->tp1, 0.001);
        $this->assertEqualsWithDelta(122.75, (float) $signal->tp2, 0.001);
        $this->assertEqualsWithDelta(123.50, (float) $signal->tp3, 0.001);
    }

    public function test_accepts_raw_body_without_json_content_type(): void
    {
        // TradingView posts the JSON as text/plain (no application/json header).
        $this->call('POST', '/api/webhooks/tradingview', [], [], [],
            ['CONTENT_TYPE' => 'text/plain'], json_encode($this->payload))
            ->assertStatus(202);

        $this->assertSame(1, Signal::count());
        $this->assertSame('A+', Signal::first()->grade);
    }

    public function test_exit_signals_are_not_stored_by_default(): void
    {
        $this->postJson('/api/webhooks/tradingview', array_merge($this->payload, ['signal' => 'exit']))
            ->assertStatus(202);

        // Exit alerts are acknowledged but not stored (store_exit_signals=false).
        $this->assertSame(0, Signal::count());
        $this->assertDatabaseHas('tradingview_webhooks', ['signal' => 'exit', 'status' => 'processed']);
    }

    public function test_duplicate_webhook_is_ignored(): void
    {
        // Freeze time so both posts fall in the same dedupe bucket.
        $this->travelTo(now());

        $this->postJson('/api/webhooks/tradingview', $this->payload)->assertStatus(202);
        $this->postJson('/api/webhooks/tradingview', $this->payload)
            ->assertStatus(200)
            ->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, TradingViewWebhook::count());
        $this->assertSame(1, Signal::count());
    }

    public function test_secret_is_not_persisted(): void
    {
        $this->postJson('/api/webhooks/tradingview', $this->payload)->assertStatus(202);

        $webhook = TradingViewWebhook::first();
        $this->assertArrayNotHasKey('secret', $webhook->raw_payload);
    }
}
