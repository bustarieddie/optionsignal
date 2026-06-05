<?php

use App\Http\Controllers\Account\ApiTokenController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\McpAuditController;
use App\Http\Controllers\Admin\PerformanceController;
use App\Http\Controllers\Admin\RiskDefaultsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebhookLogController;
use App\Http\Controllers\BacktestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\language\LanguageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PineScriptController;
use App\Http\Controllers\RiskSettingController;
use App\Http\Controllers\SignalController;
use App\Http\Controllers\StrategyController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Locale switch (kept from the Sneat template).
Route::get('/lang/{locale}', [LanguageController::class, 'swap']);

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Signals (read for everyone; Viewer included)
    Route::get('/signals', [SignalController::class, 'index'])->name('signals.index');
    Route::get('/signals/{signal}', [SignalController::class, 'show'])->name('signals.show');

    // Watchlist
    Route::middleware('permission:manage watchlist')->group(function () {
        Route::resource('watchlist', WatchlistController::class)->except(['show']);
    });

    // Strategies
    Route::middleware('permission:manage strategies')->group(function () {
        Route::resource('strategies', StrategyController::class);
    });

    // Trade journal
    Route::middleware('permission:manage trades')->group(function () {
        Route::resource('trades', TradeController::class);
        Route::post('trades/{trade}/notes', [TradeController::class, 'storeNote'])->name('trades.notes.store');
    });

    // Backtesting
    Route::middleware('permission:manage backtests')->group(function () {
        Route::resource('backtests', BacktestController::class)->except(['edit', 'update']);
    });

    // Risk management
    Route::middleware('permission:manage risk')->group(function () {
        Route::get('/risk', [RiskSettingController::class, 'edit'])->name('risk.edit');
        Route::put('/risk', [RiskSettingController::class, 'update'])->name('risk.update');
    });

    // Account
    Route::get('/account/profile', [ProfileController::class, 'edit'])->name('account.profile');
    Route::get('/account/api-tokens', [ApiTokenController::class, 'index'])->name('account.api-tokens');
    Route::post('/account/api-tokens', [ApiTokenController::class, 'store'])->name('account.api-tokens.store');
    Route::delete('/account/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('account.api-tokens.destroy');

    // Pine script template
    Route::get('/pine-script', [PineScriptController::class, 'index'])->name('pine-script');

    // Latest cached quotes (for initial dashboard load; live updates come via Reverb)
    Route::get('/quotes', function () {
        return response()->json(\Illuminate\Support\Facades\Cache::get('quotes.latest', []));
    })->name('quotes.latest');

    // Notifications (database channel)
    Route::get('/notifications/{notification}/go', [NotificationController::class, 'go'])->name('notifications.go');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Admin panel
    Route::middleware('permission:access admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users')->middleware('permission:manage users');
        Route::put('/users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles')->middleware('permission:manage users');
        Route::get('/webhooks', [WebhookLogController::class, 'index'])->name('webhooks')->middleware('permission:view webhook logs');
        Route::get('/mcp-audit', [McpAuditController::class, 'index'])->name('mcp-audit')->middleware('permission:view mcp audit');
        Route::get('/performance', [PerformanceController::class, 'index'])->name('performance');
        Route::get('/risk-defaults', [RiskDefaultsController::class, 'index'])->name('risk-defaults');
    });
});
