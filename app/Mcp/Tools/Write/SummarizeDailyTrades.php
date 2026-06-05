<?php

namespace App\Mcp\Tools\Write;

use App\Mcp\Concerns\AuditsMcpCalls;
use App\Mcp\Concerns\RequiresWritePermission;
use App\Models\Trade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Title('Summarize Daily Trades')]
#[Description("Aggregate the authenticated user's trades for a given date (defaults to today): counts, realised P&L and win rate. Requires the 'mcp:write' token ability.")]
class SummarizeDailyTrades extends Tool
{
    use AuditsMcpCalls;
    use RequiresWritePermission;

    protected string $name = 'summarize_daily_trades';

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->audited($request, true, function () use ($request) {
            $this->assertCanWrite($request);

            $validated = $request->validate([
                'date' => ['nullable', 'date'],
            ]);

            $date = isset($validated['date'])
                ? Carbon::parse($validated['date'])
                : Carbon::today();

            $userId = $request->user()->getAuthIdentifier();

            // Trades opened that day.
            $opened = Trade::query()
                ->where('user_id', $userId)
                ->whereDate('opened_at', $date)
                ->get();

            // Trades closed that day drive realised P&L and win rate.
            $closed = Trade::query()
                ->where('user_id', $userId)
                ->where('status', 'closed')
                ->whereDate('closed_at', $date)
                ->get();

            $wins = $closed->filter(fn (Trade $t) => (float) $t->pnl > 0)->count();
            $losses = $closed->filter(fn (Trade $t) => (float) $t->pnl < 0)->count();
            $closedCount = $closed->count();

            return Response::structured([
                'date'           => $date->toDateString(),
                'opened_count'   => $opened->count(),
                'closed_count'   => $closedCount,
                'wins'           => $wins,
                'losses'         => $losses,
                'win_rate'       => $closedCount > 0 ? round($wins / $closedCount * 100, 1) : 0.0,
                'realised_pnl'   => round((float) $closed->sum('pnl'), 2),
                'tickers_traded' => $opened->pluck('ticker')->merge($closed->pluck('ticker'))->unique()->values()->all(),
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()
                ->description('Optional date to summarise, format YYYY-MM-DD. Defaults to today.'),
        ];
    }
}
