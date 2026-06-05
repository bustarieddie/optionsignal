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

#[Title('Get Signal By Ticker')]
#[Description("Return the authenticated user's most recent signals for a single ticker symbol, as a safe projection. Read-only.")]
#[IsReadOnly]
class GetSignalByTicker extends Tool
{
    use AuditsMcpCalls;
    use RequiresWritePermission;

    protected string $name = 'get_signal_by_ticker';

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->audited($request, false, function () use ($request) {
            $this->assertCanRead($request);

            $validated = $request->validate([
                'ticker' => ['required', 'string', 'max:15'],
            ]);

            $ticker = strtoupper(trim($validated['ticker']));

            $signals = Signal::query()
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->where('ticker', $ticker)
                ->with(['strategy', 'scores', 'optionSuggestion'])
                ->orderByDesc('occurred_at')
                ->limit(25)
                ->get();

            $httpRequest = request();

            $projected = $signals
                ->map(fn (Signal $signal) => (new SignalResource($signal))->toArray($httpRequest))
                ->all();

            return Response::structured([
                'ticker'  => $ticker,
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
            'ticker' => $schema->string()
                ->description('The ticker symbol to look up, e.g. "AAPL".')
                ->required(),
        ];
    }
}
