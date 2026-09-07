<?php

namespace App\Domain\Ontology;

enum RuleCategory: string
{
    case Documentation = 'documentation';
    case Threshold = 'threshold';
    case Escalation = 'escalation';
    case ReviewThreshold = 'review_threshold';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
