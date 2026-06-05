<?php

namespace App\DataTransferObjects;

/**
 * The additive confidence-score breakdown for a signal.
 */
class ScoreBreakdown
{
    /** @param array<int, array{component: string, points: int, detail: array}> $components */
    public function __construct(
        public readonly array $components,
        public readonly int $total,
    ) {
    }
}
