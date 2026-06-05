<?php

namespace App\Jobs;

use App\DataTransferObjects\WebhookPayload;
use App\Events\SignalProcessed;
use App\Models\Signal;
use App\Models\TradingViewWebhook;
use App\Models\User;
use App\Notifications\SignalNotification;
use App\Services\Risk\RiskEvaluator;
use App\Services\Signals\ConfidenceScorer;
use App\Services\Signals\OptionContractSuggester;
use App\Services\Signals\SignalGrader;
use App\Services\Signals\StrategyMatcher;
use App\Services\Signals\TradeLevelCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTradingViewSignal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $webhookId)
    {
    }

    public function handle(
        ConfidenceScorer $scorer,
        SignalGrader $grader,
        StrategyMatcher $matcher,
        OptionContractSuggester $suggester,
        RiskEvaluator $risk,
        TradeLevelCalculator $levels,
    ): void {
        $webhook = TradingViewWebhook::find($this->webhookId);
        if (! $webhook || $webhook->status !== 'received') {
            return;
        }

        $payload = WebhookPayload::fromArray(array_merge($webhook->raw_payload, [
            'ticker' => $webhook->ticker,
            'timeframe' => $webhook->timeframe,
            'signal' => $webhook->signal,
        ]));

        // Skip exit alerts entirely unless explicitly enabled — keep the feed to entries.
        if ($payload->isExit() && ! config('signals.store_exit_signals')) {
            $webhook->update(['status' => 'processed', 'processed_at' => now()]);

            return;
        }

        $breakdown = $scorer->score($payload);
        $grade = $payload->isExit() ? 'ignore' : $grader->grade($breakdown->total);

        // Entry / stop / take-profit levels (skip exits). Prefer explicit levels
        // from the payload, otherwise compute from ATR (or % fallback).
        $lvl = null;
        if (! $payload->isExit()) {
            $direction = $payload->isCall() ? 'call' : 'put';
            if ($payload->stopLoss !== null && $payload->tp1 !== null) {
                $lvl = [
                    'entry' => $payload->price,
                    'stop_loss' => $payload->stopLoss,
                    'tp1' => $payload->tp1,
                    'tp2' => $payload->tp2,
                    'tp3' => $payload->tp3,
                ];
            } else {
                $lvl = $levels->compute($direction, $payload->price, $payload->atr);
            }
        }

        // Fan the signal out to every user subscribed to this ticker
        // (active watchlist entry). This keeps signals per-user for the dashboard.
        $users = User::whereHas('watchlists', function ($q) use ($payload) {
            $q->where('ticker', $payload->ticker)->where('active', true);
        })->get();

        foreach ($users as $user) {
            $strategy = $matcher->match($payload, $user->id);
            $watchlist = $user->watchlists()->where('ticker', $payload->ticker)->first();

            $signal = Signal::create([
                'user_id' => $user->id,
                'strategy_id' => $strategy?->id,
                'watchlist_id' => $watchlist?->id,
                'tradingview_webhook_id' => $webhook->id,
                'ticker' => $payload->ticker,
                'timeframe' => $payload->timeframe,
                'signal_type' => $payload->signal,
                'price' => $payload->price,
                'ema9' => $payload->ema9,
                'ema21' => $payload->ema21,
                'rsi' => $payload->rsi,
                'rsi_ma' => $payload->rsiMa,
                'vwap' => $payload->vwap,
                'volume_status' => $payload->volumeStatus,
                'atr' => $payload->atr,
                'stop_loss' => $lvl['stop_loss'] ?? null,
                'tp1' => $lvl['tp1'] ?? null,
                'tp2' => $lvl['tp2'] ?? null,
                'tp3' => $lvl['tp3'] ?? null,
                'grade' => $grade,
                'total_score' => $breakdown->total,
                'status' => 'active',
                'occurred_at' => $payload->timestamp ?? now(),
            ]);

            foreach ($breakdown->components as $component) {
                $signal->scores()->create($component);
            }

            if ($criteria = $suggester->suggestFor($signal, $payload)) {
                $signal->optionSuggestion()->create($criteria);
            }

            // Annotate with the current risk posture (decision support only).
            $riskCheck = $risk->evaluate($user);

            event(new SignalProcessed($signal));

            // Notify only on actionable, non-exit signals.
            if (! $payload->isExit() && $grader->isActionable($breakdown->total)) {
                $user->notify(new SignalNotification($signal, $riskCheck['reasons']));
            }
        }

        $webhook->update(['status' => 'processed', 'processed_at' => now()]);
    }
}
