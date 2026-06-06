<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // secret validated by ValidateWebhookSecret middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowed = config('signals.allowed_symbols');
        $tickerRule = ['required', 'string', 'max:10'];
        if (! empty($allowed)) {
            $tickerRule[] = Rule::in($allowed);
        }

        return [
            'secret' => ['required', 'string'],
            'ticker' => $tickerRule,
            'timeframe' => ['required', 'string', 'max:10'],
            'signal' => ['required', 'string', Rule::in(['buy_call', 'buy_put', 'exit', 'BUY_CALL', 'BUY_PUT', 'EXIT'])],
            'price' => ['nullable', 'numeric'],
            'strategy' => ['nullable', 'string', 'max:120'],
            'ema9' => ['nullable', 'numeric'],
            'ema21' => ['nullable', 'numeric'],
            'rsi' => ['nullable', 'numeric'],
            'rsi_ma' => ['nullable', 'numeric'],
            'vwap' => ['nullable', 'numeric'],
            'atr' => ['nullable', 'numeric'],
            'stop_loss' => ['nullable', 'numeric'],
            'tp1' => ['nullable', 'numeric'],
            'tp2' => ['nullable', 'numeric'],
            'tp3' => ['nullable', 'numeric'],
            'volume_status' => ['nullable', 'string', 'max:20'],
            'rs_status' => ['nullable', 'string', 'max:20'],
            'htf_trend' => ['nullable', 'string', Rule::in(['bullish', 'bearish', 'neutral'])],
            'sr_clear' => ['nullable', 'boolean'],
            'timestamp' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('ticker')) {
            $this->merge(['ticker' => strtoupper((string) $this->input('ticker'))]);
        }
        if ($this->has('signal')) {
            $this->merge(['signal' => strtolower((string) $this->input('signal'))]);
        }
    }
}
