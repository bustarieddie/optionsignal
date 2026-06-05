<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Strategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StrategyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $strategies = Strategy::with('rules')
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->where('active', true)
            ->get()
            ->map(fn (Strategy $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'timeframes' => $s->timeframes,
                'rules' => $s->rules->map(fn ($r) => [
                    'type' => $r->rule_type,
                    'component' => $r->component,
                    'condition' => $r->condition_key,
                ]),
            ]);

        return response()->json(['data' => $strategies]);
    }
}
