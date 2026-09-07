<?php

namespace App\Domain\Audit;

enum AuditEventType: string
{
    case LoginSuccess = 'auth.login.success';
    case LoginFailed = 'auth.login.failed';
    case Logout = 'auth.logout';

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
}
