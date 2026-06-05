<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiskSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_daily_loss' => ['required', 'numeric', 'min:0'],
            'max_trades_per_day' => ['required', 'integer', 'min:0'],
            'risk_per_trade_pct' => ['required', 'numeric', 'min:0'],
            'max_position_size' => ['required', 'numeric', 'min:0'],
            'stop_loss_pct' => ['required', 'numeric', 'min:0'],
            'take_profit_pct' => ['required', 'numeric', 'min:0'],
            'cooldown_minutes_after_loss' => ['required', 'integer', 'min:0'],
            'no_trade_window_start' => ['nullable', 'date_format:H:i'],
            'no_trade_window_end' => ['nullable', 'date_format:H:i'],
        ];
    }
}
