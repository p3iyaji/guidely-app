<?php

namespace App\Domain\Pupils;

enum SafeguardingSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
