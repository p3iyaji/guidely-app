<?php

namespace App\Domain\Evidence;

enum EvidenceLifecycle: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
