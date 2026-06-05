<?php

namespace App\Services\Quotes;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches real-time(ish) stock quotes from a configurable provider.
 * Decision-support only — quotes may be delayed.
 */
class QuoteService
{
    /** Resolve the active provider (auto picks finnhub when a key exists). */
    public function provider(): string
    {
        $provider = config('quotes.provider', 'auto');

        if ($provider === 'auto') {
            return config('services.finnhub.key') ? 'finnhub' : 'yahoo';
        }

        return $provider;
    }

    /**
     * @param  array<int, string>  $tickers
     * @return array<string, array{price: float|null, change: float|null, change_pct: float|null, at: string}>
     */
    public function fetch(array $tickers): array
    {
        $tickers = array_values(array_unique(array_map('strtoupper', $tickers)));

        if (empty($tickers)) {
            return [];
        }

        return match ($this->provider()) {
            'finnhub' => $this->finnhub($tickers),
            'yahoo' => $this->yahoo($tickers),
            default => [],
        };
    }

    /** Finnhub /quote — one call per symbol (free tier allows 60/min). */
    private function finnhub(array $tickers): array
    {
        $key = config('services.finnhub.key');
        $out = [];

        foreach ($tickers as $ticker) {
            try {
                $r = Http::timeout(8)->get('https://finnhub.io/api/v1/quote', [
                    'symbol' => $ticker,
                    'token' => $key,
                ]);

                if ($r->successful() && $r->json('c')) {
                    $out[$ticker] = $this->row(
                        price: (float) $r->json('c'),
                        change: $r->json('d') !== null ? (float) $r->json('d') : null,
                        pct: $r->json('dp') !== null ? (float) $r->json('dp') : null,
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("Finnhub quote failed for {$ticker}: {$e->getMessage()}");
            }
        }

        return $out;
    }

    /** Yahoo Finance v8 chart — keyless fallback, one call per symbol. */
    private function yahoo(array $tickers): array
    {
        $out = [];

        foreach ($tickers as $ticker) {
            try {
                $r = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (OptionSignalPro)'])
                    ->get("https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}", [
                        'interval' => '1m',
                        'range' => '1d',
                    ]);

                $meta = $r->json('chart.result.0.meta');
                if ($r->successful() && $meta && isset($meta['regularMarketPrice'])) {
                    $price = (float) $meta['regularMarketPrice'];
                    $prev = isset($meta['chartPreviousClose']) ? (float) $meta['chartPreviousClose'] : null;
                    $change = $prev !== null ? $price - $prev : null;
                    $pct = ($prev !== null && $prev != 0.0) ? ($change / $prev) * 100 : null;

                    $out[$ticker] = $this->row($price, $change, $pct);
                }
            } catch (\Throwable $e) {
                Log::warning("Yahoo quote failed for {$ticker}: {$e->getMessage()}");
            }
        }

        return $out;
    }

    private function row(float $price, ?float $change, ?float $pct): array
    {
        return [
            'price' => round($price, 2),
            'change' => $change !== null ? round($change, 2) : null,
            'change_pct' => $pct !== null ? round($pct, 2) : null,
            'at' => now()->toIso8601String(),
        ];
    }
}
