<?php

namespace App\Domain\Evidence;

enum EvidenceSource: string
{
    case Import = 'import';
    case Capture = 'capture';
    case Connector = 'connector';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_values(array_column(self::cases(), 'value'));
    }
}
