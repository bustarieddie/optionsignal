<?php

namespace App\Mcp\Tools\Read;

use App\Mcp\Concerns\AuditsMcpCalls;
use App\Mcp\Concerns\RequiresWritePermission;
use App\Models\Watchlist;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Get Watchlist')]
#[Description("Return the authenticated user's active watchlist tickers with sector and timeframe context. Read-only.")]
#[IsReadOnly]
class GetWatchlist extends Tool
{
    use AuditsMcpCalls;
    use RequiresWritePermission;

    protected string $name = 'get_watchlist';

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->audited($request, false, function () use ($request) {
            $this->assertCanRead($request);

            $items = Watchlist::query()
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->orderByDesc('active')
                ->orderBy('ticker')
                ->get()
                ->map(fn (Watchlist $w) => [
                    'ticker'             => $w->ticker,
                    'company'            => $w->company,
                    'sector'            => $w->sector,
                    'optionable'         => (bool) $w->optionable,
                    'preferred_timeframe' => $w->preferred_timeframe,
                    'active'             => (bool) $w->active,
                    'notes'              => $w->notes,
                ])
                ->all();

            return Response::structured([
                'count'     => count($items),
                'watchlist' => $items,
            ]);
        });
    }
}
