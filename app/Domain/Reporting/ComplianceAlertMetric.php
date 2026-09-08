<?php

namespace App\Domain\Reporting;

enum ComplianceAlertMetric: string
{
    case LatenessRate = 'lateness_rate';
    case GapDensity = 'gap_density';

    public function label(): string
    {
        return match ($this) {
            self::LatenessRate => 'Lateness',
            self::GapDensity => 'Gap density',
        };
    }
}
