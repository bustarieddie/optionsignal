<?php

namespace App\Console\Commands;

use App\Events\QuoteUpdated;
use App\Models\Watchlist;
use App\Services\Quotes\QuoteService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PollQuotes extends Command
{
    protected $signature = 'quotes:poll {--watch : Loop continuously at the configured interval}';

    protected $description = 'Fetch real-time quotes for active watchlist tickers and broadcast them';

    public function handle(QuoteService $quotes): int
    {
        if (! config('quotes.enabled', true)) {
            $this->warn('Quote feed is disabled (QUOTE_FEED_ENABLED=false).');

            return self::SUCCESS;
        }

        $interval = max(5, (int) config('quotes.poll_seconds', 15));
        $this->info("Quote feed using provider [{$quotes->provider()}].");

        do {
            $tickers = Watchlist::where('active', true)
                ->distinct()
                ->orderBy('ticker')
                ->pluck('ticker')
                ->all();

            if (empty($tickers)) {
                $this->line('No active watchlist tickers.');
            } else {
                $data = $quotes->fetch($tickers);

                if (! empty($data)) {
                    Cache::put('quotes.latest', $data, config('quotes.cache_ttl', 120));
                    QuoteUpdated::dispatch($data);
                    $this->line('['.now()->format('H:i:s').'] broadcast '.count($data).' quotes: '.implode(', ', array_keys($data)));
                } else {
                    $this->line('No quotes returned (market closed or provider unavailable).');
                }
            }

            if ($this->option('watch')) {
                sleep($interval);
            }
        } while ($this->option('watch'));

        return self::SUCCESS;
    }
}
