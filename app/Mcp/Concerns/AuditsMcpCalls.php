<?php

namespace App\Mcp\Concerns;

use App\Models\McpAuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Throwable;

/**
 * Wraps an MCP tool's execution in auditing: captures duration, redacts
 * sensitive arguments, and always writes an McpAuditLog row — on success,
 * permission denial, or unexpected error.
 *
 * Tools call $this->audited($request, isWrite, fn () => ...) and return the
 * result. The closure's return value is passed straight through.
 */
trait AuditsMcpCalls
{
    /**
     * Argument keys whose values must never be persisted to the audit log.
     *
     * @var array<int, string>
     */
    protected array $redactedKeys = ['secret', 'password', 'token', 'api_key', 'apikey'];

    /**
     * Run the given callback, recording a single audit row regardless of outcome.
     *
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    protected function audited(Request $request, bool $isWrite, callable $callback): mixed
    {
        $start = hrtime(true);
        $status = 'ok';

        try {
            return $callback();
        } catch (AuthorizationException $e) {
            $status = 'denied';
            throw $e;
        } catch (Throwable $e) {
            $status = 'error';
            throw $e;
        } finally {
            $this->writeAuditLog($request, $isWrite, $status, $start);
        }
    }

    /**
     * Persist the audit record. Never allowed to break the tool response, so
     * any failure to log is swallowed.
     */
    protected function writeAuditLog(Request $request, bool $isWrite, string $status, int $startedAtHrtime): void
    {
        try {
            $user = $request->user();
            $token = method_exists($user, 'currentAccessToken') ? $user?->currentAccessToken() : null;

            McpAuditLog::create([
                'user_id'       => $user?->getAuthIdentifier(),
                'token_id'      => $token?->id,
                'tool_name'     => $this->name(),
                'is_write'      => $isWrite,
                'arguments'     => $this->redactArguments($request->all()),
                'result_status' => $status,
                'duration_ms'   => (int) round((hrtime(true) - $startedAtHrtime) / 1_000_000),
                'source_ip'     => request()->ip(),
                'created_at'    => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Redact sensitive keys from the arguments before they are stored.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function redactArguments(array $arguments): array
    {
        foreach ($arguments as $key => $value) {
            if (in_array(strtolower((string) $key), $this->redactedKeys, true)) {
                $arguments[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $arguments[$key] = $this->redactArguments($value);
            }
        }

        return $arguments;
    }
}
