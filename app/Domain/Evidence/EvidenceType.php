<?php

namespace App\Domain\Evidence;

enum EvidenceType: string
{
    case Observation = 'observation';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
