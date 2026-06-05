<?php

use App\Mcp\Servers\OptionSignalServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP (AI) Routes
|--------------------------------------------------------------------------
|
| This file is auto-loaded by laravel/mcp's service provider (it calls
| Route::group([], base_path('routes/ai.php')) when the file exists), so no
| edit to bootstrap/app.php is required.
|
| The OptionSignal Pro server is exposed over the streamable HTTP transport at
| POST /mcp/optionsignal and authenticated with Sanctum. Tools then enforce
| per-token abilities: read tools need 'mcp:read', write tools need
| 'mcp:write'. Every call is audited to mcp_audit_logs.
|
*/

Mcp::web('/mcp/optionsignal', OptionSignalServer::class)
    ->middleware('auth:sanctum');
