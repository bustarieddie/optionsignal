<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe, secret-free projection of a Signal. Reused by the REST API, broadcast
 * events and MCP tools so redaction lives in one place.
 */
class SignalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticker' => $this->ticker,
            'timeframe' => $this->timeframe,
            'signal_type' => $this->signal_type,
            'price' => $this->price,
            'ema9' => $this->ema9,
            'ema21' => $this->ema21,
            'rsi' => $this->rsi,
            'rsi_ma' => $this->rsi_ma,
            'vwap' => $this->vwap,
            'volume_status' => $this->volume_status,
            'grade' => $this->grade,
            'total_score' => $this->total_score,
            'status' => $this->status,
            'color' => $this->colorCode(),
            'levels' => [
                'entry' => $this->price,
                'stop_loss' => $this->stop_loss,
                'tp1' => $this->tp1,
                'tp2' => $this->tp2,
                'tp3' => $this->tp3,
                'atr' => $this->atr,
            ],
            'strategy' => $this->whenLoaded('strategy', fn () => $this->strategy?->name),
            'scores' => $this->whenLoaded('scores', fn () => $this->scores->map(fn ($s) => [
                'component' => $s->component,
                'points' => $s->points,
            ])),
            'option_suggestion' => $this->whenLoaded('optionSuggestion', fn () => $this->optionSuggestion ? [
                'contract_type' => $this->optionSuggestion->contract_type,
                'delta_range' => $this->optionSuggestion->suggested_delta_min . '–' . $this->optionSuggestion->suggested_delta_max,
                'expiry' => $this->optionSuggestion->suggested_expiry,
                'liquidity_note' => $this->optionSuggestion->liquidity_note,
                'risk_note' => $this->optionSuggestion->risk_note,
            ] : null),
            'occurred_at' => optional($this->occurred_at)->toIso8601String(),
        ];
    }
}
