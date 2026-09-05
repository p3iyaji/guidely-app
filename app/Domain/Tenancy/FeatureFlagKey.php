<?php

namespace App\Domain\Tenancy;

enum FeatureFlagKey: string
{
    case TrustDashboard = 'trust_dashboard';
    case Connectors = 'connectors';
    case AdvancedDocumentationPacks = 'advanced_documentation_packs';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
