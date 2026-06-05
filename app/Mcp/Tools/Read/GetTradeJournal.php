<?php

namespace App\Mcp\Tools\Read;

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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Get Trade Journal')]
#[Description("Return the authenticated user's trade journal entries, optionally filtered by an opened-at date range (from/to, YYYY-MM-DD). Read-only.")]
#[IsReadOnly]
class GetTradeJournal extends Tool
{
    use AuditsMcpCalls;
    use RequiresWritePermission;

    protected string $name = 'get_trade_journal';

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->audited($request, false, function () use ($request) {
            $this->assertCanRead($request);

            $validated = $request->validate([
                'from' => ['nullable', 'date'],
                'to'   => ['nullable', 'date'],
            ]);

            $query = Trade::query()
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->with('strategy:id,name')
                ->orderByDesc('opened_at');

            if (! empty($validated['from'])) {
                $query->where('opened_at', '>=', Carbon::parse($validated['from'])->startOfDay());
            }

            if (! empty($validated['to'])) {
                $query->where('opened_at', '<=', Carbon::parse($validated['to'])->endOfDay());
            }

            $trades = $query->limit(200)->get()->map(fn (Trade $t) => [
                'id'            => $t->id,
                'ticker'        => $t->ticker,
                'direction'     => $t->direction,
                'strategy'      => $t->strategy?->name,
                'setup_name'    => $t->setup_name,
                'signal_grade'  => $t->signal_grade,
                'status'        => $t->status,
                'entry_price'   => $t->entry_price,
                'exit_price'    => $t->exit_price,
                'quantity'      => $t->quantity,
                'pnl'           => $t->pnl,
                'is_win'        => $t->status === 'closed' ? $t->isWin() : null,
                'emotion_score' => $t->emotion_score,
                'opened_at'     => optional($t->opened_at)->toIso8601String(),
                'closed_at'     => optional($t->closed_at)->toIso8601String(),
            ])->all();

            return Response::structured([
                'from'   => $validated['from'] ?? null,
                'to'     => $validated['to'] ?? null,
                'count'  => count($trades),
                'trades' => $trades,
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()
                ->description('Optional start date (inclusive), format YYYY-MM-DD.'),
            'to' => $schema->string()
                ->description('Optional end date (inclusive), format YYYY-MM-DD.'),
        ];
    }
}
