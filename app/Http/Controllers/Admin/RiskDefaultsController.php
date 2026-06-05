<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class RiskDefaultsController extends Controller
{
    /**
     * Read-only reference of the platform's default risk-management settings.
     * These seed a new user's risk_settings row on registration.
     */
    public function index(): View
    {
        $defaults = config('risk.defaults', []);

        return view('content.osp.admin.risk-defaults', compact('defaults'));
    }
}
