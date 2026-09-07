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
     * Humanised result label for API/UI (never treat Uncovered as success).
     */
    public function label(): string
    {
        return match ($this) {
            self::Met => 'Met',
            self::Unmet => 'Not met',
            self::Insufficient => 'Insufficient',
            self::Uncovered => 'Uncovered',
            self::Escalated => 'Escalated',
            self::ReviewRequired => 'Review required',
        };
    }

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
