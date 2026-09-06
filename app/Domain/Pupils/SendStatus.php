<?php

namespace App\Domain\Pupils;

enum SendStatus: string
{
    case SenSupport = 'sen_support';
    case Ehcp = 'ehcp';
    case Neither = 'neither';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
