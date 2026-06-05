<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SignalResource;
use App\Models\Signal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SignalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $signals = Signal::where('user_id', $request->user()->id)
            ->with(['strategy', 'scores', 'optionSuggestion'])
            ->latest('occurred_at')
            ->paginate(25);

        return SignalResource::collection($signals);
    }

    public function byTicker(Request $request, string $ticker): AnonymousResourceCollection
    {
        $signals = Signal::where('user_id', $request->user()->id)
            ->where('ticker', strtoupper($ticker))
            ->with(['strategy', 'scores', 'optionSuggestion'])
            ->latest('occurred_at')
            ->paginate(25);

        return SignalResource::collection($signals);
    }
}
