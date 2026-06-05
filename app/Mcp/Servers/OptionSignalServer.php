<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Read\GetLatestSignals;
use App\Mcp\Tools\Read\GetSignalByTicker;
use App\Mcp\Tools\Read\GetStrategyPerformance;
use App\Mcp\Tools\Read\GetTradeJournal;
use App\Mcp\Tools\Read\GetWatchlist;
use App\Mcp\Tools\Write\AnalyzeFailedTrades;
use App\Mcp\Tools\Write\CreateTradeNote;
use App\Mcp\Tools\Write\SummarizeDailyTrades;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('OptionSignal Pro')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
OptionSignal Pro MCP server — read-only-by-default decision-support for an
options-trading journal and signal engine.

All tools operate strictly in the context of the authenticated Sanctum user;
they never expose another user's data, and never return secrets (webhook
secrets, token hashes, raw passwords).

Capabilities:
- Read tools (require the token ability `mcp:read`): get_watchlist,
  get_latest_signals, get_signal_by_ticker, get_trade_journal,
  get_strategy_performance.
- Write tools (require the token ability `mcp:write`): create_trade_note,
  summarize_daily_trades, analyze_failed_trades. These only annotate or
  aggregate the user's own data.

This server is decision-support only. It does NOT place orders, connect to a
broker, move money, or execute trades. Nothing it returns is financial advice;
always verify the live option chain manually before acting. Every tool call is
audited.
MARKDOWN)]
class OptionSignalServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Read-only (mcp:read)
        GetWatchlist::class,
        GetLatestSignals::class,
        GetSignalByTicker::class,
        GetTradeJournal::class,
        GetStrategyPerformance::class,
        // Write (mcp:write)
        CreateTradeNote::class,
        SummarizeDailyTrades::class,
        AnalyzeFailedTrades::class,
    ];

    /**
     * @var array<int, class-string>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string>
     */
    protected array $prompts = [];
}
