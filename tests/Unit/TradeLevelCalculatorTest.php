<?php

namespace Tests\Unit;

use App\Services\Signals\TradeLevelCalculator;
use Tests\TestCase;

class TradeLevelCalculatorTest extends TestCase
{
    public function test_atr_based_call_levels(): void
    {
        // sl_atr 1.0, tp 1.0/1.5/2.0 (config defaults). entry 100, atr 2.
        $lvl = (new TradeLevelCalculator)->compute('call', 100.0, 2.0);

        $this->assertSame(100.0, $lvl['entry']);
        $this->assertSame(98.0, $lvl['stop_loss']);  // 100 - 1*2
        $this->assertSame(102.0, $lvl['tp1']);        // 100 + 1*2
        $this->assertSame(103.0, $lvl['tp2']);        // 100 + 1.5*2
        $this->assertSame(104.0, $lvl['tp3']);        // 100 + 2*2
    }

    public function test_put_inverts_direction(): void
    {
        $lvl = (new TradeLevelCalculator)->compute('put', 100.0, 2.0);

        $this->assertSame(102.0, $lvl['stop_loss']);  // stop above for puts
        $this->assertSame(98.0, $lvl['tp1']);
        $this->assertSame(97.0, $lvl['tp2']);
        $this->assertSame(96.0, $lvl['tp3']);
    }

    public function test_percent_fallback_without_atr(): void
    {
        // sl_pct 1.0, tp 1.0/1.5/2.0 (%). entry 200, no atr.
        $lvl = (new TradeLevelCalculator)->compute('call', 200.0, null);

        $this->assertSame(198.0, $lvl['stop_loss']);  // -1%
        $this->assertSame(202.0, $lvl['tp1']);        // +1%
        $this->assertSame(203.0, $lvl['tp2']);        // +1.5%
        $this->assertSame(204.0, $lvl['tp3']);        // +2%
    }

    public function test_null_entry_returns_null(): void
    {
        $this->assertNull((new TradeLevelCalculator)->compute('call', null, 2.0));
    }
}
