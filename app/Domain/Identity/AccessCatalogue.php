<?php

namespace App\Domain\Identity;

final class AccessCatalogue
{
    /**
     * Built-in Permission definitions. Keys are stable product contracts.
     *
     * @return list<array{key: string, label: string, description: string, group: string}>
     */
    public static function systemPermissions(): array
    {
        return [
            [
                'key' => 'manage_users',
                'label' => 'Manage Users',
                'description' => 'Create, update, deactivate, and reset passwords for Tenant Users.',
                'group' => 'Administration',
            ],
            [
                'key' => 'manage_schools',
                'label' => 'Manage Schools',
                'description' => 'Create, update, and delete Schools in the Tenant.',
                'group' => 'Administration',
            ],
            [
                'key' => 'manage_roles',
                'label' => 'Manage Roles',
                'description' => 'Create and update custom Roles and their Permission assignments.',
                'group' => 'Administration',
            ],
            [
                'key' => 'manage_permissions',
                'label' => 'Manage Permissions',
                'description' => 'Create and update custom Permissions for this Tenant.',
                'group' => 'Administration',
            ],
            [
                'key' => 'manage_feature_flags',
                'label' => 'Manage feature flags',
                'description' => 'Enable or disable Tenant feature flags.',
                'group' => 'Administration',
            ],
            [
                'key' => 'manage_provision_terms',
                'label' => 'Manage Provision terms',
                'description' => 'Create, update, and delete Provision terms on the effective Ontology.',
                'group' => 'Administration',
            ],
            [
                'key' => 'manage_connectors',
                'label' => 'Manage connectors',
                'description' => 'Configure MIS connectors for the Tenant.',
                'group' => 'Administration',
            ],
            [
                'key' => 'view_pilot_toolkit',
                'label' => 'View Pilot toolkit',
                'description' => 'Open Pilot toolkit resources.',
                'group' => 'Administration',
            ],
            [
                'key' => 'import_pupils',
                'label' => 'Import Pupils',
                'description' => 'Upload Pupil import files.',
                'group' => 'Pupils',
            ],
            [
                'key' => 'manage_pupils',
                'label' => 'Manage Pupils',
                'description' => 'Create and update Pupil records.',
                'group' => 'Pupils',
            ],
            [
                'key' => 'capture_evidence',
                'label' => 'Capture evidence',
                'description' => 'Submit observations, interventions, and responses.',
                'group' => 'Evidence',
            ],
            [
                'key' => 'view_evidence_base',
                'label' => 'View Evidence Base',
                'description' => 'Open a Pupil Evidence Base.',
                'group' => 'Evidence',
            ],
            [
                'key' => 'manage_review_cycles',
                'label' => 'Manage Review Cycles',
                'description' => 'Create and close Review Cycles.',
                'group' => 'Reviews',
            ],
            [
                'key' => 'view_gaps',
                'label' => 'View Gaps',
                'description' => 'List documentation Gaps.',
                'group' => 'Reviews',
            ],
            [
                'key' => 'generate_outputs',
                'label' => 'Generate documentation outputs',
                'description' => 'Build and download documentation outputs.',
                'group' => 'Reviews',
            ],
            [
                'key' => 'view_school_report',
                'label' => 'View School Report',
                'description' => 'Open the School Report.',
                'group' => 'Reporting',
            ],
            [
                'key' => 'view_trust_dashboard',
                'label' => 'View Trust Dashboard',
                'description' => 'Open Trust Dashboard and related Indicators.',
                'group' => 'Reporting',
            ],
            [
                'key' => 'view_alerts',
                'label' => 'View compliance alerts',
                'description' => 'List compliance alerts for allowed scopes.',
                'group' => 'Reporting',
            ],
        ];
    }

    /**
     * Built-in Role definitions. Keys match {@see Role} values.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function systemRoles(): array
    {
        return [
            [
                'key' => Role::Teacher->value,
                'label' => 'Teacher',
                'description' => 'Captures evidence for assigned Pupils.',
            ],
            [
                'key' => Role::SupportStaff->value,
                'label' => 'Support Staff',
                'description' => 'Captures evidence for assigned Pupils.',
            ],
            [
                'key' => Role::Senco->value,
                'label' => 'SENCO',
                'description' => 'Coordinates SEND documentation for the School.',
            ],
            [
                'key' => Role::SchoolLeader->value,
                'label' => 'School Leader',
                'description' => 'Reviews School-level SEND reporting.',
            ],
            [
                'key' => Role::TenantAdmin->value,
                'label' => 'Tenant Admin',
                'description' => 'Administers Users, Schools, and Tenant settings.',
            ],
            [
                'key' => Role::TrustSendLead->value,
                'label' => 'Trust SEND Lead',
                'description' => 'Views Trust-wide SEND Indicators across Schools.',
            ],
            [
                'key' => Role::TrustExecutive->value,
                'label' => 'Trust Executive',
                'description' => 'Views Trust-wide SEND Indicators across Schools.',
            ],
            [
                'key' => Role::PlatformOperator->value,
                'label' => 'Platform Operator',
                'description' => 'Operates the platform outside a Tenant.',
            ],
        ];
    }

    /**
     * Default Permission keys attached to a built-in Role.
     *
     * @return list<string>
     */
    public static function defaultPermissionKeysFor(Role $role): array
    {
        return match ($role) {
            Role::Teacher, Role::SupportStaff => [
                'capture_evidence',
                'view_evidence_base',
            ],
            Role::Senco => [
                'import_pupils',
                'manage_pupils',
                'capture_evidence',
                'view_evidence_base',
                'manage_review_cycles',
                'view_gaps',
                'generate_outputs',
                'view_school_report',
                'view_alerts',
            ],
            Role::SchoolLeader => [
                'manage_review_cycles',
                'view_school_report',
                'generate_outputs',
            ],
            Role::TenantAdmin => [
                'manage_users',
                'manage_schools',
                'manage_roles',
                'manage_permissions',
                'manage_feature_flags',
                'manage_provision_terms',
                'manage_connectors',
                'view_pilot_toolkit',
                'import_pupils',
                'manage_pupils',
            ],
            Role::TrustSendLead, Role::TrustExecutive => [
                'view_trust_dashboard',
                'view_alerts',
            ],
            Role::PlatformOperator => [
                'view_pilot_toolkit',
            ],
        };
    }
}
