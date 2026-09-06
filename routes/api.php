<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\FeatureFlagController;
use App\Http\Controllers\Api\V1\FeatureStubController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PilotTenantController;
use App\Http\Controllers\Api\V1\PilotToolkitController;
use App\Http\Controllers\Api\V1\PupilAssignmentController;
use App\Http\Controllers\Api\V1\PupilController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TenantSsoController;
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

        Route::get('/trust-dashboard', [FeatureStubController::class, 'trustDashboard'])
            ->middleware('feature:trust_dashboard')
            ->name('api.v1.trust-dashboard');
        Route::get('/connectors', [FeatureStubController::class, 'connectors'])
            ->middleware('feature:connectors')
            ->name('api.v1.connectors');
        Route::get('/advanced-documentation-packs', [FeatureStubController::class, 'advancedDocumentationPacks'])
            ->middleware('feature:advanced_documentation_packs')
            ->name('api.v1.advanced-documentation-packs');
    });
});
