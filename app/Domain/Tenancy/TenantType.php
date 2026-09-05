<?php

namespace App\Domain\Tenancy;

enum TenantType: string
{
    case School = 'school';
    case Trust = 'trust';
}
