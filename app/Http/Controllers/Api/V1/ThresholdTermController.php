<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\ThresholdTerm;
use App\Domain\Pupils\Pupil;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreThresholdTermRequest;
use App\Http\Requests\Api\V1\UpdateThresholdTermRequest;
use App\Http\Resources\Api\V1\ThresholdTermResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ThresholdTermController extends Controller
{
    public const IN_USE_CODE = 'threshold_term_in_use';

    public function __construct(private AuditWriter $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (
            $user === null
            || (
                ! $user->can('create', EvidenceRecord::class)
                && ! $user->can('create', Pupil::class)
                && ! $user->can('viewAny', ThresholdTerm::class)
            )
        ) {
            throw new AuthorizationException;
        }

        $canManage = $user->can('viewAny', ThresholdTerm::class);

        $terms = ThresholdTerm::query()
            ->when(
                $canManage,
                fn ($query) => $query->forVersion(
                    app(EffectiveOntologyVersion::class)->id() ?? '',
                ),
                fn ($query) => $query->forTenant(),
            )
            ->orderBy('sort_order')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return ThresholdTermResource::collection($terms);
    }

    public function store(StoreThresholdTermRequest $request): JsonResponse
    {
        $version = app(EffectiveOntologyVersion::class)->resolve();

        abort_if($version === null, 422, 'No published Ontology version is available for this Tenant.');

        $term = ThresholdTerm::query()->create([
            ...$request->safe()->only(['code', 'label', 'is_active', 'sort_order']),
            'ontology_version_id' => $version->id,
        ]);

        $this->audit->record(
            AuditEventType::ThresholdTermCreated,
            $request,
            $request->user(),
            resourceType: 'threshold_term',
            resourceId: $term->id,
        );

        return (new ThresholdTermResource($term))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ThresholdTerm $thresholdTerm): ThresholdTermResource
    {
        $this->authorize('view', $thresholdTerm);

        return new ThresholdTermResource($thresholdTerm);
    }

    public function update(UpdateThresholdTermRequest $request, ThresholdTerm $thresholdTerm): ThresholdTermResource
    {
        $thresholdTerm->update($request->safe()->only(['code', 'label', 'is_active', 'sort_order']));

        $this->audit->record(
            AuditEventType::ThresholdTermUpdated,
            $request,
            $request->user(),
            resourceType: 'threshold_term',
            resourceId: $thresholdTerm->id,
        );

        return new ThresholdTermResource($thresholdTerm->refresh());
    }

    public function destroy(Request $request, ThresholdTerm $thresholdTerm): Response|JsonResponse
    {
        $this->authorize('delete', $thresholdTerm);

        if ($thresholdTerm->isInUse()) {
            return response()->json([
                'message' => 'This Threshold term is in use and cannot be deleted. Deactivate it instead.',
                'code' => self::IN_USE_CODE,
            ], 409);
        }

        $termId = $thresholdTerm->id;
        $thresholdTerm->delete();

        $this->audit->record(
            AuditEventType::ThresholdTermDeleted,
            $request,
            $request->user(),
            resourceType: 'threshold_term',
            resourceId: $termId,
        );

        return response()->noContent();
    }
}
