<?php

namespace App\Domain\Reviews;

enum ReviewCycleType: string
{
    case AnnualReview = 'annual_review';
    case Interim = 'interim';
    case Other = 'other';

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
            self::AnnualReview => 'Annual Review',
            self::Interim => 'Interim',
            self::Other => 'Other',
        };
    }
}
