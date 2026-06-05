<?php

namespace App\Services\Signals;

class SignalGrader
{
    /**
     * Map a confidence score to a letter grade using config/signals.php cutoffs.
     * Scores below the lowest band grade as "ignore".
     */
    public function grade(int $score): string
    {
        $grades = config('signals.grades');
        arsort($grades); // highest threshold first

        foreach ($grades as $grade => $threshold) {
            if ($score >= $threshold) {
                return $grade;
            }
        }

        return 'ignore';
    }

    public function isActionable(int $score): bool
    {
        return $score >= (int) config('signals.min_actionable_score', 60);
    }
}
