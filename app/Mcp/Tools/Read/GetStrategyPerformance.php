<?php

namespace App\Mcp\Tools\Read;

use App\Mcp\Concerns\AuditsMcpCalls;
use App\Mcp\Concerns\RequiresWritePermission;
use App\Services\Analytics\PerformanceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Get Strategy Performance')]
#[Description("Return per-strategy aggregate performance (trades, P&L, win rate) for the authenticated user, optionally filtered to a single strategy_id. Read-only.")]
#[IsReadOnly]
class GetStrategyPerformance extends Tool
{
    use AuditsMcpCalls;
    use RequiresWritePermission;

    protected string $name = 'get_strategy_performance';

    public function handle(Request $request, PerformanceService $performance): Response|ResponseFactory
    {
        return $this->audited($request, false, function () use ($request, $performance) {
            $this->assertCanRead($request);

            $validated = $request->validate([
                'strategy_id' => ['nullable', 'integer'],
            ]);

            $user = $request->user();
            $rows = $performance->strategyPerformance($user);

            if (! empty($validated['strategy_id'])) {
                $strategyId = (int) $validated['strategy_id'];
                $name = $user->strategies()->whereKey($strategyId)->value('name');

                $rows = array_values(array_filter(
                    $rows,
                    fn (array $row) => isset($name) && $row['strategy'] === $name
                ));
            }

            return Response::structured([
                'strategy_id' => $validated['strategy_id'] ?? null,
                'count'       => count($rows),
                'performance' => $rows,
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'strategy_id' => $schema->integer()
                ->description('Optional strategy id to filter performance to a single strategy.'),
        ];
    }
}
