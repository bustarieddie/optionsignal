<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTradeRequest extends FormRequest
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
            'ticker' => ['required', 'string', 'max:10'],
            'direction' => ['required', Rule::in(['call', 'put'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'entry_price' => ['required', 'numeric', 'min:0'],
            'exit_price' => ['nullable', 'numeric', 'min:0'],
            'setup_name' => ['nullable', 'string', 'max:255'],
            'signal_grade' => ['nullable', Rule::in(['A+', 'A', 'B', 'C'])],
            'contract_details' => ['nullable', 'string', 'max:255'],
            'reason_for_entry' => ['nullable', 'string'],
            'signal_id' => ['nullable', 'integer', 'exists:signals,id'],
            'opened_at' => ['required', 'date'],
            'closed_at' => ['nullable', 'date'],
            'screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }
}
