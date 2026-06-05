<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->watchlists()
            ->orderBy('ticker')
            ->get(['ticker', 'company', 'sector', 'optionable', 'preferred_timeframe', 'active']);

        return response()->json(['data' => $items]);
    }
}
