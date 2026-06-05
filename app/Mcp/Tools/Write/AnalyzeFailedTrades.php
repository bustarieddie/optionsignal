<?php

namespace App\Mcp\Tools\Write;

use App\Mcp\Concerns\AuditsMcpCalls;
use App\Mcp\Concerns\RequiresWritePermission;
use App\Models\Trade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Title('Analyze Failed Trades')]
#[Description("Return the authenticated user's recent losing trades with their mistake notes and lessons, plus a roll-up of common mistakes, to support post-mortem analysis. Requires the 'mcp:write' token ability.")]
class AnalyzeFailedTrades extends Tool
{
    use AuditsMcpCalls;
    use RequiresWritePermission;

    protected string $name = 'analyze_failed_trades';

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->audited($request, true, function () use ($request) {
            $this->assertCanWrite($request);

            $validated = $request->validate([
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $limit = (int) ($validated['limit'] ?? 20);

            $losers = Trade::query()
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->where('status', 'closed')
                ->where('pnl', '<', 0)
                ->orderByDesc('closed_at')
                ->limit($limit)
                ->get();

            $trades = $losers->map(fn (Trade $t) => [
                'id'             => $t->id,
                'ticker'         => $t->ticker,
                'direction'      => $t->direction,
                'setup_name'     => $t->setup_name,
                'signal_grade'   => $t->signal_grade,
                'pnl'            => $t->pnl,
                'emotion_score'  => $t->emotion_score,
                'reason_for_exit' => $t->reason_for_exit,
                'mistake_notes'  => $t->mistake_notes,
                'lessons'        => $t->lessons,
                'closed_at'      => optional($t->closed_at)->toIso8601String(),
            ])->all();

            return Response::structured([
                'count'        => count($trades),
                'total_loss'   => round((float) $losers->sum('pnl'), 2),
                'avg_loss'     => $losers->isNotEmpty() ? round((float) $losers->avg('pnl'), 2) : 0.0,
                'with_mistake_notes' => $losers->whereNotNull('mistake_notes')->count(),
                'trades'       => $trades,
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('Maximum number of recent losing trades to analyse (1-100, default 20).'),
        ];
    }
}
