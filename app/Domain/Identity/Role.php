<?php

namespace App\Domain\Identity;

enum Role: string
{
    case Teacher = 'teacher';
    case SupportStaff = 'support_staff';
    case Senco = 'senco';
    case SchoolLeader = 'school_leader';
    case TenantAdmin = 'tenant_admin';
    case TrustSendLead = 'trust_send_lead';
    case TrustExecutive = 'trust_executive';
    case PlatformOperator = 'platform_operator';

    public function isTrustRole(): bool
    {
        return match ($this) {
            self::TrustSendLead, self::TrustExecutive => true,
            default => false,
        };
    }

    public function isTenantAssignable(): bool
    {
        return match ($this) {
            self::PlatformOperator => false,
            default => true,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
