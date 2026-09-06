<?php

namespace App\Domain\Pupils;

enum AssignmentSource: string
{
    case Senco = 'senco';
    case Connector = 'connector';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
