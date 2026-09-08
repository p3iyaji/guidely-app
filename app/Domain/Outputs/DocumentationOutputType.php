<?php

namespace App\Domain\Outputs;

enum DocumentationOutputType: string
{
    case ReviewSummary = 'review_summary';
    case EhcpPack = 'ehcp_pack';
    case TribunalPack = 'tribunal_pack';
    case InspectionPack = 'inspection_pack';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isAdvanced(): bool
    {
        return match ($this) {
            self::TribunalPack, self::InspectionPack => true,
            self::ReviewSummary, self::EhcpPack => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ReviewSummary => 'Review summary',
            self::EhcpPack => 'EHCP pack',
            self::TribunalPack => 'Tribunal pack',
            self::InspectionPack => 'Inspection pack',
        };
    }
}
