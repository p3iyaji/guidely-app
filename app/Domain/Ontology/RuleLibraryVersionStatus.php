<?php

namespace App\Domain\Ontology;

enum RuleLibraryVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
