<?php

namespace App\Domain\Sre;

/**
 * Allowed Determination result values for FR-26 SRE evaluations.
 */
enum DeterminationResult: string
{
    case Met = 'met';
    case Unmet = 'unmet';
    case Insufficient = 'insufficient';
    case Uncovered = 'uncovered';
    case Escalated = 'escalated';
    case ReviewRequired = 'review_required';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Map a Pilot Rule outcome.result string onto a DeterminationResult.
     */
    public static function fromRuleOutcome(string $outcomeResult): ?self
    {
        return self::tryFrom($outcomeResult);
    }
}
