<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\PerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function __construct(private readonly PerformanceService $performance)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->performance->dashboardSummary($request->user()),
        ]);
    }
}
