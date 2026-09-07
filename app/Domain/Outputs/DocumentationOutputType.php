<?php

namespace App\Domain\Outputs;

enum DocumentationOutputType: string
{
    case ReviewSummary = 'review_summary';
    case EhcpPack = 'ehcp_pack';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::ReviewSummary => 'Review summary',
            self::EhcpPack => 'EHCP pack',
        };
    }
}
