<?php

namespace App\DataTransferObjects;

use Illuminate\Support\Carbon;

/**
 * Normalised, validated TradingView webhook payload.
 */
class WebhookPayload
{
    public function __construct(
        public readonly string $ticker,
        public readonly string $timeframe,
        public readonly string $signal,        // buy_call | buy_put | exit
        public readonly ?float $price,
        public readonly ?string $strategy,
        public readonly ?float $ema9,
        public readonly ?float $ema21,
        public readonly ?float $rsi,
        public readonly ?float $rsiMa,
        public readonly ?float $vwap,
        public readonly ?string $volumeStatus, // above_average | below_average
        public readonly ?string $rsStatus,     // leading_both | lagging_both | mixed
        public readonly ?string $htfTrend,     // bullish | bearish | null
        public readonly ?bool $srClear,        // clean support/resistance
        public readonly ?float $atr,
        public readonly ?float $stopLoss,
        public readonly ?float $tp1,
        public readonly ?float $tp2,
        public readonly ?float $tp3,
        public readonly ?Carbon $timestamp,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ticker: strtoupper((string) ($data['ticker'] ?? '')),
            timeframe: self::normaliseTimeframe((string) ($data['timeframe'] ?? '')),
            signal: strtolower((string) ($data['signal'] ?? '')),
            price: isset($data['price']) ? (float) $data['price'] : null,
            strategy: $data['strategy'] ?? null,
            ema9: isset($data['ema9']) ? (float) $data['ema9'] : null,
            ema21: isset($data['ema21']) ? (float) $data['ema21'] : null,
            rsi: isset($data['rsi']) ? (float) $data['rsi'] : null,
            rsiMa: isset($data['rsi_ma']) ? (float) $data['rsi_ma'] : null,
            vwap: isset($data['vwap']) ? (float) $data['vwap'] : null,
            volumeStatus: $data['volume_status'] ?? null,
            rsStatus: $data['rs_status'] ?? null,
            htfTrend: $data['htf_trend'] ?? null,
            srClear: isset($data['sr_clear']) ? filter_var($data['sr_clear'], FILTER_VALIDATE_BOOL) : null,
            atr: isset($data['atr']) ? (float) $data['atr'] : null,
            stopLoss: isset($data['stop_loss']) ? (float) $data['stop_loss'] : null,
            tp1: isset($data['tp1']) ? (float) $data['tp1'] : null,
            tp2: isset($data['tp2']) ? (float) $data['tp2'] : null,
            tp3: isset($data['tp3']) ? (float) $data['tp3'] : null,
            // Convert the exchange-timezone bar time to the app timezone so it
            // stores/displays consistently (e.g. 09:35 ET → 21:35 KL).
            timestamp: ! empty($data['timestamp'])
                ? Carbon::parse($data['timestamp'])->setTimezone(config('app.timezone'))
                : null,
            raw: $data,
        );
    }

    public function isCall(): bool
    {
        return $this->signal === 'buy_call';
    }

    public function isPut(): bool
    {
        return $this->signal === 'buy_put';
    }

    public function isExit(): bool
    {
        return $this->signal === 'exit';
    }

    /** Normalise "5", "5m", "5min" → "5m"; "60"/"1h" → "1h". */
    private static function normaliseTimeframe(string $tf): string
    {
        $tf = strtolower(trim($tf));

        return match ($tf) {
            '3', '3min', '3m' => '3m',
            '5', '5min', '5m' => '5m',
            '15', '15min', '15m' => '15m',
            '60', '1h', '60min', '1hour' => '1h',
            default => $tf,
        };
    }
}
