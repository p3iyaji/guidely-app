<?php

namespace App\Domain\Pupils;

enum DocumentationStatus: string
{
    case Ready = 'ready';
    case Gaps = 'gaps';
    case Uncovered = 'uncovered';
    case NotStarted = 'not-started';
    case Evaluating = 'evaluating';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
