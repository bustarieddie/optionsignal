<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TradingViewWebhook;
use Illuminate\View\View;

class WebhookLogController extends Controller
{
    /**
     * Paginate inbound TradingView webhooks (latest first). The view renders
     * safePayload() so the shared webhook secret is never exposed.
     */
    public function index(): View
    {
        $webhooks = TradingViewWebhook::query()
            ->latest('created_at')
            ->paginate(25);

        return view('content.osp.admin.webhooks', compact('webhooks'));
    }
}
