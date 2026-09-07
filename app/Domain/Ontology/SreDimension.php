<?php

namespace App\Domain\Ontology;

/**
 * Four FR-26 Statutory Reasoning Engine dimensions.
 */
enum SreDimension: string
{
    case SequentialCompliance = 'Sequential Compliance';
    case EvidentialSufficiency = 'Evidential Sufficiency';
    case Proportionality = 'Proportionality';
    case OutcomeProgression = 'Outcome Progression';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
