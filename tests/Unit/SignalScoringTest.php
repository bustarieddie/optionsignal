<?php

namespace Tests\Unit;

use App\DataTransferObjects\WebhookPayload;
use App\Services\Signals\ConfidenceScorer;
use App\Services\Signals\SignalGrader;
use Tests\TestCase;

class SignalScoringTest extends TestCase
{
    private function payload(array $overrides = []): WebhookPayload
    {
        return WebhookPayload::fromArray(array_merge([
            'ticker' => 'NVDA', 'timeframe' => '5m', 'signal' => 'buy_call',
            'price' => 120.50, 'ema9' => 121.20, 'ema21' => 120.80,
            'rsi' => 58, 'rsi_ma' => 52, 'vwap' => 119.90,
            'volume_status' => 'above_average', 'htf_trend' => 'bullish', 'sr_clear' => true,
        ], $overrides));
    }

    public function test_fully_confirmed_call_scores_100_and_grades_a_plus(): void
    {
        $breakdown = (new ConfidenceScorer)->score($this->payload());

        $this->assertSame(100, $breakdown->total);
        $this->assertSame('A+', (new SignalGrader)->grade($breakdown->total));
        $this->assertCount(6, $breakdown->components);
    }

    public function test_missing_optional_confirmations_lower_the_score(): void
    {
        // Drop HTF and S/R confirmations → 100 - 15 - 10 = 75 → grade B.
        $breakdown = (new ConfidenceScorer)->score($this->payload([
            'htf_trend' => 'bearish', 'sr_clear' => false,
        ]));

        $this->assertSame(75, $breakdown->total);
        $this->assertSame('B', (new SignalGrader)->grade($breakdown->total));
    }

    public function test_put_inverts_directional_checks(): void
    {
        // Same numbers but a put: EMA9>EMA21, RSI>MA, price>VWAP all now FAIL.
        $breakdown = (new ConfidenceScorer)->score($this->payload([
            'signal' => 'buy_put',
        ]));

        // Only volume (+15) and htf? htf bullish but put wants bearish → fail. sr_clear true (+10) → 25.
        $this->assertSame(25, $breakdown->total);
        $this->assertSame('ignore', (new SignalGrader)->grade($breakdown->total));
    }

    public function test_grader_thresholds(): void
    {
        $grader = new SignalGrader;
        $this->assertSame('A+', $grader->grade(95));
        $this->assertSame('A', $grader->grade(85));
        $this->assertSame('B', $grader->grade(72));
        $this->assertSame('C', $grader->grade(61));
        $this->assertSame('ignore', $grader->grade(40));
        $this->assertFalse($grader->isActionable(50));
        $this->assertTrue($grader->isActionable(60));
    }
}
