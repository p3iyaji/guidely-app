<?php

namespace App\Domain\Reporting;

enum ComplianceAlertScope: string
{
    case Trust = 'trust';
    case School = 'school';
}
