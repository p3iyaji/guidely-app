<?php

namespace App\Domain\Connectors;

enum ConnectorType: string
{
    case PilotStub = 'pilot_stub';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
