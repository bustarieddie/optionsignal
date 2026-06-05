<?php

use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\SignalController;
use App\Http\Controllers\Api\StrategyController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\TradingViewWebhookController;
use App\Http\Controllers\Api\WatchlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// TradingView webhook — secret validated from JSON body, rate limited.
Route::post('/webhooks/tradingview', [TradingViewWebhookController::class, 'store'])
    ->middleware(['webhook.secret', 'throttle:60,1'])
    ->name('api.webhooks.tradingview');

// Authenticated REST API (Sanctum token abilities).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/signals', [SignalController::class, 'index'])->name('api.signals.index');
    Route::get('/signals/{ticker}', [SignalController::class, 'byTicker'])->name('api.signals.ticker');

    Route::get('/strategies', [StrategyController::class, 'index'])->name('api.strategies.index');
    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('api.watchlist.index');

    Route::get('/trades', [TradeController::class, 'index'])->name('api.trades.index');
    Route::post('/trades', [TradeController::class, 'store'])
        ->middleware('ability:trades:write')
        ->name('api.trades.store');

    Route::get('/performance', [PerformanceController::class, 'index'])->name('api.performance.index');
});
