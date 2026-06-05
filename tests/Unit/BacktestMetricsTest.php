<?php

namespace Tests\Unit;

use App\Services\Backtest\MetricsCalculator;
use Tests\TestCase;

class BacktestMetricsTest extends TestCase
{
    public function test_computes_core_metrics(): void
    {
        $trades = [
            ['pnl' => 200, 'ticker' => 'NVDA', 'timeframe' => '5m', 'grade' => 'A', 'setup' => 'breakout'],
            ['pnl' => -100, 'ticker' => 'TSLA', 'timeframe' => '5m', 'grade' => 'B', 'setup' => 'pullback'],
            ['pnl' => 300, 'ticker' => 'NVDA', 'timeframe' => '15m', 'grade' => 'A', 'setup' => 'breakout'],
            ['pnl' => -50, 'ticker' => 'AMD', 'timeframe' => '5m', 'grade' => 'C', 'setup' => 'pullback'],
        ];

        $m = (new MetricsCalculator)->compute($trades);

        $this->assertSame(4, $m['total_trades']);
        $this->assertSame(2, $m['wins']);
        $this->assertSame(2, $m['losses']);
        $this->assertSame(50.0, $m['win_rate']);
        $this->assertSame(250.0, $m['avg_win']);          // (200+300)/2
        $this->assertSame(75.0, $m['avg_loss']);           // (100+50)/2
        $this->assertSame(round(500 / 150, 2), $m['profit_factor']);
        $this->assertSame('NVDA', $m['best_ticker']);      // 200+300 = 500
        $this->assertSame('breakout', $m['best_setup']);
    }

    public function test_max_drawdown_is_peak_to_trough(): void
    {
        // Equity curve: +500 (peak) → -300 (=200) → +100 (=300). Max DD = 500-200 = 300.
        $trades = [
            ['pnl' => 500], ['pnl' => -300], ['pnl' => 100],
        ];

        $m = (new MetricsCalculator)->compute($trades);

        $this->assertSame(300.0, $m['max_drawdown']);
    }

    public function test_profit_factor_null_when_no_losses(): void
    {
        $m = (new MetricsCalculator)->compute([['pnl' => 100], ['pnl' => 50]]);

        $this->assertNull($m['profit_factor']);
        $this->assertSame(100.0, $m['win_rate']);
    }
}
