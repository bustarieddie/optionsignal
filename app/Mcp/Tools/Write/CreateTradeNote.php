<?php

namespace App\Mcp\Tools\Write;

use App\Mcp\Concerns\AuditsMcpCalls;
use App\Mcp\Concerns\RequiresWritePermission;
use App\Models\Trade;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Title('Create Trade Note')]
#[Description("Attach a note (source='mcp') to one of the authenticated user's trades. Requires the 'mcp:write' token ability.")]
class CreateTradeNote extends Tool
{
    use AuditsMcpCalls;
    use RequiresWritePermission;

    protected string $name = 'create_trade_note';

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->audited($request, true, function () use ($request) {
            $this->assertCanWrite($request);

            $validated = $request->validate([
                'trade_id' => ['required', 'integer'],
                'body'     => ['required', 'string', 'max:5000'],
            ]);

            $user = $request->user();

            // Only allow notes on trades owned by the authenticated user.
            $trade = Trade::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->find($validated['trade_id']);

            if ($trade === null) {
                throw new AuthorizationException(
                    'Trade not found or not owned by the authenticated user.'
                );
            }

            $note = $trade->notes()->create([
                'user_id' => $user->getAuthIdentifier(),
                'body'    => $validated['body'],
                'source'  => 'mcp',
            ]);

            return Response::structured([
                'created'  => true,
                'note_id'  => $note->id,
                'trade_id' => $trade->id,
                'source'   => $note->source,
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trade_id' => $schema->integer()
                ->description('The id of the trade to annotate. Must belong to the authenticated user.')
                ->required(),
            'body' => $schema->string()
                ->description('The note body text.')
                ->required(),
        ];
    }
}
