<?php

namespace App\Domain\Audit;

enum AuditEventType: string
{
    case LoginSuccess = 'auth.login.success';
    case LoginFailed = 'auth.login.failed';
    case Logout = 'auth.logout';

    case AccessRoleCreated = 'access_role.created';
    case AccessRoleUpdated = 'access_role.updated';
    case AccessRoleDeleted = 'access_role.deleted';
    case AccessPermissionCreated = 'access_permission.created';
    case AccessPermissionUpdated = 'access_permission.updated';
    case AccessPermissionDeleted = 'access_permission.deleted';

    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeactivated = 'user.deactivated';
    case UserPasswordReset = 'user.password_reset';

    case SchoolCreated = 'school.created';
    case SchoolUpdated = 'school.updated';
    case SchoolDeleted = 'school.deleted';

    case PupilCreated = 'pupil.created';
    case PupilUpdated = 'pupil.updated';
    case PupilDeleted = 'pupil.deleted';
    case PupilAssignmentUpdated = 'pupil.assignment.updated';
    case PupilEvidenceViewed = 'pupil.evidence.viewed';

    case TenantCreated = 'tenant.created';
    case TenantCohortUpdated = 'tenant.cohort.updated';
    case FeatureFlagUpdated = 'feature_flag.updated';
    case TenantSsoUpdated = 'tenant.sso.updated';

    case EvidenceObservationCreated = 'evidence.observation.created';
    case EvidenceObservationUpdated = 'evidence.observation.updated';
    case EvidenceObservationSubmitted = 'evidence.observation.submitted';
    case EvidenceInterventionCreated = 'evidence.intervention.created';
    case EvidenceInterventionUpdated = 'evidence.intervention.updated';
    case EvidenceInterventionSubmitted = 'evidence.intervention.submitted';
    case EvidenceResponseCreated = 'evidence.response.created';
    case EvidenceResponseUpdated = 'evidence.response.updated';
    case EvidenceResponseSubmitted = 'evidence.response.submitted';
    case EvidenceReviewNoteCreated = 'evidence.review_note.created';
    case SreOverrideCreated = 'sre.override.created';
    case LibraryPublished = 'ontology.library.published';
    case ProvisionTermCreated = 'ontology.provision_term.created';
    case ProvisionTermUpdated = 'ontology.provision_term.updated';
    case ProvisionTermDeleted = 'ontology.provision_term.deleted';
    case NeedTermCreated = 'ontology.need_term.created';
    case NeedTermUpdated = 'ontology.need_term.updated';
    case NeedTermDeleted = 'ontology.need_term.deleted';
    case SettingTermCreated = 'ontology.setting_term.created';
    case SettingTermUpdated = 'ontology.setting_term.updated';
    case SettingTermDeleted = 'ontology.setting_term.deleted';
    case OutcomeTermCreated = 'ontology.outcome_term.created';
    case OutcomeTermUpdated = 'ontology.outcome_term.updated';
    case OutcomeTermDeleted = 'ontology.outcome_term.deleted';
    case ThresholdTermCreated = 'ontology.threshold_term.created';
    case ThresholdTermUpdated = 'ontology.threshold_term.updated';
    case ThresholdTermDeleted = 'ontology.threshold_term.deleted';
    case RelationshipMappingCreated = 'ontology.relationship_mapping.created';
    case RelationshipMappingUpdated = 'ontology.relationship_mapping.updated';
    case RelationshipMappingDeleted = 'ontology.relationship_mapping.deleted';
    case ReviewCycleCreated = 'review_cycle.created';
    case ReviewCycleClosed = 'review_cycle.closed';
    case DocumentationOutputGenerated = 'documentation_output.generated';
    case DocumentationOutputDownloaded = 'documentation_output.downloaded';
    case ConnectorUpdated = 'connector.updated';
    case ComplianceAlertCreated = 'compliance_alert.created';
    case ComplianceAlertResolved = 'compliance_alert.resolved';
    case SafeguardingSignalUpserted = 'safeguarding_signal.upserted';
}
