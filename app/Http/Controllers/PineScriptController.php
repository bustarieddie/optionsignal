<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PineScriptController extends Controller
{
    public function index(): View
    {
        $scripts = [
            [
                'key' => 'default',
                'name' => 'EMA 9/21 RSI Scalping',
                'file' => 'optionsignal-pro.pine',
                'strategy' => 'EMA_9_21_RSI',
                'code' => file_get_contents(resource_path('pine/optionsignal-pro.pine')),
            ],
            [
                'key' => 'confluence',
                'name' => 'Confluence ORB Squeeze',
                'file' => 'optionsignal-confluence.pine',
                'strategy' => 'Confluence ORB Squeeze',
                'code' => file_get_contents(resource_path('pine/optionsignal-confluence.pine')),
            ],
            [
                'key' => 'vssce',
                'name' => 'VSS + Chandelier + RSI-MA',
                'file' => 'optionsignal-vss-ce.pine',
                'strategy' => 'VSS Chandelier RSI-MA',
                'code' => file_get_contents(resource_path('pine/optionsignal-vss-ce.pine')),
            ],
        ];

        $pine = $scripts[0]['code'];

        $sampleJson = json_encode([
            'secret' => 'your_webhook_secret',
            'ticker' => 'NVDA',
            'timeframe' => '5m',
            'signal' => 'buy_call',
            'price' => 120.50,
            'strategy' => 'EMA_9_21_RSI',
            'ema9' => 121.20,
            'ema21' => 120.80,
            'rsi' => 58,
            'rsi_ma' => 52,
            'vwap' => 119.90,
            'volume_status' => 'above_average',
            'htf_trend' => 'bullish',
            'sr_clear' => true,
            'timestamp' => '2026-06-05T09:35:00-04:00',
        ], JSON_PRETTY_PRINT);

        return view('content.osp.pine.index', [
            'scripts' => $scripts,
            'pine' => $pine,
            'sampleJson' => $sampleJson,
            'webhookUrl' => url('/api/webhooks/tradingview'),
        ]);
    }
}
