<?php

namespace App\Mcp\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Mcp\Request;

/**
 * Token-ability assertions for MCP tools. Read tools must carry the
 * 'mcp:read' ability; write tools additionally require 'mcp:write'.
 *
 * Abilities are checked against the authenticated Sanctum personal access
 * token via tokenCan(). A missing ability raises AuthorizationException,
 * which the audit wrapper records as result_status='denied'.
 */
trait RequiresWritePermission
{
    /**
     * Assert the current token may perform read operations.
     */
    protected function assertCanRead(Request $request): void
    {
        $this->assertTokenAbility($request, 'mcp:read');
    }

    /**
     * Assert the current token may perform write operations.
     */
    protected function assertCanWrite(Request $request): void
    {
        // A write also implies the ability to read the affected resources.
        $this->assertTokenAbility($request, 'mcp:read');
        $this->assertTokenAbility($request, 'mcp:write');
    }

    /**
     * @throws AuthorizationException
     */
    protected function assertTokenAbility(Request $request, string $ability): void
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthorizationException('Unauthenticated MCP request.');
        }

        // Sanctum exposes tokenCan() on the authenticated user; fall back to
        // the access token's can() if the helper is unavailable.
        $allowed = false;

        if (method_exists($user, 'tokenCan')) {
            $allowed = $user->tokenCan($ability);
        } elseif (method_exists($user, 'currentAccessToken')) {
            $token = $user->currentAccessToken();
            $allowed = $token !== null && method_exists($token, 'can') && $token->can($ability);
        }

        if (! $allowed) {
            throw new AuthorizationException(
                "This MCP token is missing the required '{$ability}' ability."
            );
        }
    }
}
