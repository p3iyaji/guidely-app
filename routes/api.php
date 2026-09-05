<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\FeatureFlagController;
use App\Http\Controllers\Api\V1\FeatureStubController;
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

        Route::apiResource('users', UserController::class)
            ->except(['destroy'])
            ->names('api.v1.users');
        Route::patch('/users/{user}/password', [UserController::class, 'resetPassword'])
            ->name('api.v1.users.reset-password');
        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])
            ->name('api.v1.users.deactivate');

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
