<?php

namespace Tests\Feature;

use App\Events\QuoteUpdated;
use App\Models\User;
use App\Services\Quotes\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuoteFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_yahoo_driver_parses_quotes(): void
    {
        config(['quotes.provider' => 'yahoo']);
        Http::fake([
            'query1.finance.yahoo.com/*' => Http::response([
                'chart' => ['result' => [['meta' => [
                    'regularMarketPrice' => 218.66,
                    'chartPreviousClose' => 214.70,
                ]]]],
            ], 200),
        ]);

        $q = app(QuoteService::class)->fetch(['NVDA']);

        $this->assertSame('yahoo', app(QuoteService::class)->provider());
        $this->assertEqualsWithDelta(218.66, $q['NVDA']['price'], 0.001);
        $this->assertEqualsWithDelta(3.96, $q['NVDA']['change'], 0.01);
        $this->assertEqualsWithDelta(1.85, $q['NVDA']['change_pct'], 0.05);
    }

    public function test_finnhub_driver_parses_quotes(): void
    {
        config(['quotes.provider' => 'finnhub', 'services.finnhub.key' => 'test-key']);
        Http::fake([
            'finnhub.io/*' => Http::response(['c' => 311.23, 'd' => 0.96, 'dp' => 0.31], 200),
        ]);

        $q = app(QuoteService::class)->fetch(['AAPL']);

        $this->assertSame('finnhub', app(QuoteService::class)->provider());
        $this->assertEqualsWithDelta(311.23, $q['AAPL']['price'], 0.001);
        $this->assertEqualsWithDelta(0.31, $q['AAPL']['change_pct'], 0.001);
    }

    public function test_poll_command_caches_and_broadcasts(): void
    {
        Event::fake([QuoteUpdated::class]);
        config(['quotes.provider' => 'yahoo', 'quotes.enabled' => true]);
        Http::fake([
            'query1.finance.yahoo.com/*' => Http::response([
                'chart' => ['result' => [['meta' => [
                    'regularMarketPrice' => 100.0, 'chartPreviousClose' => 98.0,
                ]]]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $user->watchlists()->create(['ticker' => 'NVDA', 'optionable' => true, 'active' => true]);

        $this->artisan('quotes:poll')->assertExitCode(0);

        $this->assertArrayHasKey('NVDA', Cache::get('quotes.latest', []));
        Event::assertDispatched(QuoteUpdated::class, fn ($e) => isset($e->quotes['NVDA']));
    }
}
