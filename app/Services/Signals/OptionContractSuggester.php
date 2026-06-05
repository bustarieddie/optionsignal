<?php

namespace App\Services\Signals;

use App\DataTransferObjects\WebhookPayload;
use App\Models\Signal;

/**
 * Produces option-contract *criteria* for a graded signal. Decision-support
 * only — there is no broker or option-chain integration.
 */
class OptionContractSuggester
{
    public function suggestFor(Signal $signal, WebhookPayload $payload): ?array
    {
        if ($payload->isExit()) {
            return null;
        }

        $cfg = config('signals.option_suggestion');

        return [
            'contract_type' => $payload->isCall() ? 'call' : 'put',
            'suggested_delta_min' => $cfg['delta_min'],
            'suggested_delta_max' => $cfg['delta_max'],
            'suggested_expiry' => $cfg['expiry_hint'],
            'spread_note' => 'Target a tight bid/ask spread; avoid wide markets.',
            'liquidity_note' => $cfg['liquidity_note'],
            'risk_note' => $cfg['risk_note'],
        ];
    }
}
