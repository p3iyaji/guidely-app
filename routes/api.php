<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConnectorController;
use App\Http\Controllers\Api\V1\DeterminationOverrideController;
use App\Http\Controllers\Api\V1\DocumentationOutputController;
use App\Http\Controllers\Api\V1\DraftController;
use App\Http\Controllers\Api\V1\EvidenceController;
use App\Http\Controllers\Api\V1\FeatureFlagController;
use App\Http\Controllers\Api\V1\FeatureStubController;
use App\Http\Controllers\Api\V1\GapController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\InterventionController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NeedTermController;
use App\Http\Controllers\Api\V1\ObservationController;
use App\Http\Controllers\Api\V1\OutcomeTermController;
use App\Http\Controllers\Api\V1\PilotTenantController;
use App\Http\Controllers\Api\V1\PilotToolkitController;
use App\Http\Controllers\Api\V1\ProvisionTermController;
use App\Http\Controllers\Api\V1\PupilAssignmentController;
use App\Http\Controllers\Api\V1\PupilController;
use App\Http\Controllers\Api\V1\PupilDeterminationController;
use App\Http\Controllers\Api\V1\PupilEvidenceController;
use App\Http\Controllers\Api\V1\PupilInterventionController;
use App\Http\Controllers\Api\V1\RelationshipMappingController;
use App\Http\Controllers\Api\V1\ResponseController;
use App\Http\Controllers\Api\V1\ReviewCycleAutomationController;
use App\Http\Controllers\Api\V1\ReviewCycleController;
use App\Http\Controllers\Api\V1\ReviewNoteController;
use App\Http\Controllers\Api\V1\RuleController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SchoolReportController;
use App\Http\Controllers\Api\V1\SettingTermController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TenantSsoController;
use App\Http\Controllers\Api\V1\ThresholdTermController;
use App\Http\Controllers\Api\V1\TrustBenchmarkController;
use App\Http\Controllers\Api\V1\TrustIndicatorController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'guidely-api',
            'version' => 'v1',
        ]);
    });

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('api.v1.login');
    Route::post('/token', [AuthController::class, 'token'])
        ->middleware('throttle:login')
        ->name('api.v1.token');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.logout');
        Route::get('/me', MeController::class)->name('api.v1.me');

        Route::get('/tenant', [TenantController::class, 'show'])->name('api.v1.tenant.show');
        Route::patch('/tenant', [TenantController::class, 'update'])->name('api.v1.tenant.update');

        Route::get('/tenant/sso', [TenantSsoController::class, 'show'])
            ->name('api.v1.tenant.sso.show');
        Route::patch('/tenant/sso', [TenantSsoController::class, 'update'])
            ->name('api.v1.tenant.sso.update');

        Route::get('/tenant/feature-flags', [FeatureFlagController::class, 'index'])
            ->name('api.v1.tenant.feature-flags.index');
        Route::patch('/tenant/feature-flags', [FeatureFlagController::class, 'update'])
            ->name('api.v1.tenant.feature-flags.update');

        Route::apiResource('schools', SchoolController::class)->names('api.v1.schools');

        Route::apiResource('pupils', PupilController::class)->names('api.v1.pupils');
        Route::post('/pupils/{pupil}/assignments', [PupilAssignmentController::class, 'store'])
            ->name('api.v1.pupils.assignments.store');
        Route::delete('/pupils/{pupil}/assignments/{user}', [PupilAssignmentController::class, 'destroy'])
            ->name('api.v1.pupils.assignments.destroy');

        Route::post('/observations', [ObservationController::class, 'store'])
            ->name('api.v1.observations.store');
        Route::post('/interventions', [InterventionController::class, 'store'])
            ->name('api.v1.interventions.store');
        Route::post('/responses', [ResponseController::class, 'store'])
            ->name('api.v1.responses.store');
        Route::post('/review-notes', [ReviewNoteController::class, 'store'])
            ->name('api.v1.review-notes.store');
        Route::get('/drafts', [DraftController::class, 'index'])
            ->name('api.v1.drafts.index');
        Route::get('/drafts/{draft}', [DraftController::class, 'show'])
            ->name('api.v1.drafts.show');
        Route::patch('/drafts/{draft}', [DraftController::class, 'update'])
            ->name('api.v1.drafts.update');
        Route::post('/drafts/{draft}/submit', [DraftController::class, 'submit'])
            ->name('api.v1.drafts.submit');
        Route::get('/pupils/{pupil}/interventions', [PupilInterventionController::class, 'index'])
            ->name('api.v1.pupils.interventions.index');
        Route::get('/pupils/{pupil}/evidence', [PupilEvidenceController::class, 'index'])
            ->name('api.v1.pupils.evidence.index');
        Route::get('/pupils/{pupil}/determinations', [PupilDeterminationController::class, 'index'])
            ->name('api.v1.pupils.determinations.index');
        Route::post('/determinations/{determination}/overrides', [DeterminationOverrideController::class, 'store'])
            ->name('api.v1.determinations.overrides.store');
        Route::get('/gaps', [GapController::class, 'index'])
            ->name('api.v1.gaps.index');
        Route::get('/review-cycles', [ReviewCycleController::class, 'index'])
            ->name('api.v1.review-cycles.index');
        Route::get('/pupils/{pupil}/review-cycles', [ReviewCycleController::class, 'indexForPupil'])
            ->name('api.v1.pupils.review-cycles.index');
        Route::post('/review-cycles', [ReviewCycleController::class, 'store'])
            ->name('api.v1.review-cycles.store');
        Route::post('/pupils/{pupil}/review-cycles', [ReviewCycleController::class, 'store'])
            ->name('api.v1.pupils.review-cycles.store');
        Route::post('/review-cycles/{reviewCycle}/close', [ReviewCycleController::class, 'close'])
            ->name('api.v1.review-cycles.close');
        Route::post('/review-cycles/automation/run', [ReviewCycleAutomationController::class, 'store'])
            ->middleware('feature:review_cycle_automation')
            ->name('api.v1.review-cycles.automation.run');
        Route::get('/school-report', [SchoolReportController::class, 'show'])
            ->name('api.v1.school-report.show');
        Route::get('/documentation-outputs', [DocumentationOutputController::class, 'index'])
            ->name('api.v1.documentation-outputs.index');
        Route::post('/documentation-outputs', [DocumentationOutputController::class, 'store'])
            ->name('api.v1.documentation-outputs.store');
        Route::get('/documentation-outputs/{documentationOutput}/download', [DocumentationOutputController::class, 'download'])
            ->name('api.v1.documentation-outputs.download');
        Route::get('/documentation-outputs/{documentationOutput}', [DocumentationOutputController::class, 'show'])
            ->name('api.v1.documentation-outputs.show');
        Route::post('/pupils/{pupil}/review-cycles/{reviewCycle}/outputs', [DocumentationOutputController::class, 'store'])
            ->scopeBindings()
            ->name('api.v1.pupils.review-cycles.outputs.store');
        Route::patch('/evidence/{evidence}', [EvidenceController::class, 'update'])
            ->name('api.v1.evidence.update');
        Route::get('/evidence/{evidence}/versions', [EvidenceController::class, 'versions'])
            ->name('api.v1.evidence.versions.index');
        Route::get('/ontology/setting-terms', [SettingTermController::class, 'index'])
            ->name('api.v1.ontology.setting-terms.index');
        Route::get('/ontology/provision-terms', [ProvisionTermController::class, 'index'])
            ->name('api.v1.ontology.provision-terms.index');
        Route::get('/ontology/need-terms', [NeedTermController::class, 'index'])
            ->name('api.v1.ontology.need-terms.index');
        Route::get('/ontology/outcome-terms', [OutcomeTermController::class, 'index'])
            ->name('api.v1.ontology.outcome-terms.index');
        Route::get('/ontology/threshold-terms', [ThresholdTermController::class, 'index'])
            ->name('api.v1.ontology.threshold-terms.index');
        Route::get('/ontology/relationship-mappings', [RelationshipMappingController::class, 'index'])
            ->name('api.v1.ontology.relationship-mappings.index');
        Route::get('/ontology/rules', [RuleController::class, 'index'])
            ->name('api.v1.ontology.rules.index');

        Route::get('/import/template', [ImportController::class, 'template'])
            ->name('api.v1.import.template');
        Route::post('/import/pupils', [ImportController::class, 'uploadPupils'])
            ->name('api.v1.import.pupils');

        Route::apiResource('users', UserController::class)
            ->except(['destroy'])
            ->names('api.v1.users');
        Route::patch('/users/{user}/password', [UserController::class, 'resetPassword'])
            ->name('api.v1.users.reset-password');
        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])
            ->name('api.v1.users.deactivate');

        Route::post('/pilot/tenants', [PilotTenantController::class, 'store'])
            ->name('api.v1.pilot.tenants.store');
        Route::get('/pilot/import-template', [PilotToolkitController::class, 'importTemplate'])
            ->name('api.v1.pilot.import-template');
        Route::get('/pilot/disclaimers', [PilotToolkitController::class, 'disclaimers'])
            ->name('api.v1.pilot.disclaimers');
        Route::get('/pilot/success-metrics', [PilotToolkitController::class, 'successMetrics'])
            ->name('api.v1.pilot.success-metrics');

        Route::get('/trust-dashboard', [TrustIndicatorController::class, 'show'])
            ->middleware('feature:trust_dashboard')
            ->name('api.v1.trust-dashboard');
        Route::get('/trust-benchmark', TrustBenchmarkController::class)
            ->middleware(['feature:trust_dashboard', 'feature:portfolio_benchmarking'])
            ->name('api.v1.trust-benchmark');
        Route::get('/connectors', [ConnectorController::class, 'index'])
            ->middleware('feature:connectors')
            ->name('api.v1.connectors');
        Route::put('/connectors', [ConnectorController::class, 'upsert'])
            ->middleware('feature:connectors')
            ->name('api.v1.connectors.upsert');
        Route::post('/connectors/sync', [ConnectorController::class, 'sync'])
            ->middleware('feature:connectors')
            ->name('api.v1.connectors.sync');
        Route::get('/advanced-documentation-packs', [FeatureStubController::class, 'advancedDocumentationPacks'])
            ->middleware('feature:advanced_documentation_packs')
            ->name('api.v1.advanced-documentation-packs');
    });
});
