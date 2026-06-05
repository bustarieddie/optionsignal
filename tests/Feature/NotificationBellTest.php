<?php

namespace Tests\Feature;

use App\Models\Signal;
use App\Models\User;
use App\Notifications\SignalNotification;
use Database\Seeders\DefaultStrategySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, DefaultStrategySeeder::class]);
    }

    private function traderWithSignal(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Trader');
        $user->riskSetting()->create(config('risk.defaults'));

        $signal = Signal::create([
            'user_id' => $user->id, 'ticker' => 'NVDA', 'timeframe' => '5m',
            'signal_type' => 'buy_call', 'price' => 120.50, 'grade' => 'A+',
            'total_score' => 100, 'status' => 'active', 'occurred_at' => now(),
        ]);

        return [$user, $signal];
    }

    public function test_signal_detail_renders_with_chart(): void
    {
        [$user, $signal] = $this->traderWithSignal();

        $this->actingAs($user)->get(route('signals.show', $signal))
            ->assertOk()
            ->assertSee('tradingview-osp-chart', false)
            ->assertSee('Confidence Breakdown', false);
    }

    public function test_notification_go_marks_read_and_redirects(): void
    {
        [$user, $signal] = $this->traderWithSignal();
        $user->notify(new SignalNotification($signal));
        $note = $user->notifications()->first();

        $this->assertNull($note->read_at);

        $this->actingAs($user)->get(route('notifications.go', $note->id))
            ->assertRedirect(route('signals.show', $signal->id));

        $this->assertNotNull($note->fresh()->read_at);
    }

    public function test_log_trade_prefills_from_signal(): void
    {
        [$user, $signal] = $this->traderWithSignal();
        $signal->update(['stop_loss' => 216.66, 'tp1' => 220.66, 'tp2' => 221.66, 'tp3' => 222.66, 'atr' => 2.0]);

        $this->actingAs($user)->get(route('trades.create', ['signal' => $signal->id]))
            ->assertOk()
            ->assertSee('Pre-filled from signal', false)
            ->assertSee('value="NVDA"', false);   // ticker pre-filled
    }

    public function test_read_all_clears_unread(): void
    {
        [$user, $signal] = $this->traderWithSignal();
        $user->notify(new SignalNotification($signal));

        $this->assertSame(1, $user->unreadNotifications()->count());

        $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }
}
