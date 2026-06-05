<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    /** Abilities a user may grant to an API token. MCP write is opt-in. */
    public const ABILITIES = [
        'mcp:read' => 'MCP read-only tools (watchlist, signals, journal, performance)',
        'mcp:write' => 'MCP write tools (create note, summaries) — grant deliberately',
        'signals:read' => 'REST: read signals',
        'trades:read' => 'REST: read trades',
        'trades:write' => 'REST: create trades',
    ];

    public function index(Request $request): View
    {
        $tokens = $request->user()->tokens()->latest()->get();

        return view('content.osp.account.api-tokens', [
            'tokens' => $tokens,
            'abilities' => self::ABILITIES,
            'plainTextToken' => session('plainTextToken'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'in:' . implode(',', array_keys(self::ABILITIES))],
        ]);

        $token = $request->user()->createToken($validated['name'], $validated['abilities']);

        return redirect()
            ->route('account.api-tokens')
            ->with('plainTextToken', $token->plainTextToken)
            ->with('status', 'API token created. Copy it now — it will not be shown again.');
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return redirect()->route('account.api-tokens')->with('status', 'API token revoked.');
    }
}
