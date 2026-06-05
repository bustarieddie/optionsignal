<?php

namespace App\Mcp\Tools\Read;

use App\Http\Resources\SignalResource;
use App\Mcp\Concerns\AuditsMcpCalls;
use App\Mcp\Concerns\RequiresWritePermission;
use App\Models\Signal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Get Latest Signals')]
#[Description("Return the authenticated user's most recent trading signals as a safe, secret-free projection (grade, score, indicators, option suggestion). Read-only.")]
#[IsReadOnly]
class GetLatestSignals extends Tool
{
    use AuditsMcpCalls;
    use RequiresWritePermission;

    protected string $name = 'get_latest_signals';

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->audited($request, false, function () use ($request) {
            $this->assertCanRead($request);

            $limit = (int) $request->get('limit', 20);
            $limit = max(1, min($limit, 100));

            $signals = Signal::query()
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->with(['strategy', 'scores', 'optionSuggestion'])
                ->orderByDesc('occurred_at')
                ->limit($limit)
                ->get();

            $httpRequest = request();

            $projected = $signals
                ->map(fn (Signal $signal) => (new SignalResource($signal))->toArray($httpRequest))
                ->all();

            return Response::structured([
                'count'   => count($projected),
                'signals' => $projected,
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
                ->description('Maximum number of recent signals to return (1-100, default 20).'),
        ];
    }
}
