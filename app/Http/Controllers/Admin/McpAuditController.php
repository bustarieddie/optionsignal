<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpAuditLog;
use Illuminate\View\View;

class McpAuditController extends Controller
{
    /**
     * Paginate the MCP audit trail, newest first.
     */
    public function index(): View
    {
        $logs = McpAuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('content.osp.admin.mcp-audit', compact('logs'));
    }
}
