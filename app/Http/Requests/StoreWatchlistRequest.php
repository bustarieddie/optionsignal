<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWatchlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('ticker')) {
            $this->merge(['ticker' => strtoupper(trim($this->input('ticker')))]);
        }
    }

    public function rules(): array
    {
        return [
            'ticker' => [
                'required', 'string', 'max:10',
                Rule::unique('watchlists', 'ticker')->where(
                    fn ($q) => $q->where('user_id', $this->user()->id)
                ),
            ],
            'company' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
            'preferred_timeframe' => ['nullable', 'string', Rule::in(['3m', '5m', '15m', '1h'])],
            'optionable' => ['boolean'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
