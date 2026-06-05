<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStrategyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'timeframes' => ['nullable', 'array'],
            'timeframes.*' => ['string', Rule::in(['3m', '5m', '15m', '1h'])],
            'active' => ['boolean'],
            'rules' => ['nullable', 'array'],
            'rules.*.rule_type' => ['required_with:rules', Rule::in(['buy_call', 'buy_put', 'exit'])],
            'rules.*.component' => ['nullable', 'string', Rule::in(['ema_crossover', 'rsi', 'vwap', 'volume', 'htf', 'sr'])],
            'rules.*.condition_key' => ['required_with:rules.*.rule_type', 'string', 'max:255'],
            'rules.*.operator' => ['nullable', 'string', 'max:50'],
            'rules.*.value' => ['nullable', 'string', 'max:255'],
            'rules.*.points' => ['nullable', 'integer'],
        ];
    }
}
