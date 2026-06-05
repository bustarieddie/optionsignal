<?php

namespace Tests\Feature;

use App\Mcp\Concerns\RequiresWritePermission;
use Database\Seeders\DefaultStrategySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, DefaultStrategySeeder::class]);
    }

    public function test_mcp_endpoint_requires_authentication(): void
    {
        // No Authorization header: the Sanctum guard must reject the call
        // before any JSON-RPC handshake takes place.
        $this->postJson('/mcp/optionsignal', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'ping',
        ])->assertStatus(401);
    }

    public function test_write_permission_trait_exposes_assertion_helpers(): void
    {
        // Light structural check that the hardening trait still exposes the
        // read/write ability assertions the MCP tools rely on.
        $this->assertTrue(
            trait_exists(RequiresWritePermission::class),
            'RequiresWritePermission trait should exist.'
        );

        foreach (['assertCanRead', 'assertCanWrite', 'assertTokenAbility'] as $method) {
            $this->assertTrue(
                method_exists(RequiresWritePermission::class, $method),
                "RequiresWritePermission should define {$method}()."
            );
        }
    }
}
