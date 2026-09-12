<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\OutcomeTerm;
use App\Domain\Pupils\Pupil;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOutcomeTermRequest;
use App\Http\Requests\Api\V1\UpdateOutcomeTermRequest;
use App\Http\Resources\Api\V1\OutcomeTermResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OutcomeTermController extends Controller
{
    public const IN_USE_CODE = 'outcome_term_in_use';

    public function __construct(private AuditWriter $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (
            $user === null
            || (
                ! $user->can('create', EvidenceRecord::class)
                && ! $user->can('create', Pupil::class)
                && ! $user->can('viewAny', OutcomeTerm::class)
            )
        ) {
            throw new AuthorizationException;
        }

        $canManage = $user->can('viewAny', OutcomeTerm::class);

        $terms = OutcomeTerm::query()
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

        return OutcomeTermResource::collection($terms);
    }

    public function store(StoreOutcomeTermRequest $request): JsonResponse
    {
        $version = app(EffectiveOntologyVersion::class)->resolve();

        abort_if($version === null, 422, 'No published Ontology version is available for this Tenant.');

        $term = OutcomeTerm::query()->create([
            ...$request->safe()->only(['code', 'label', 'is_active', 'sort_order']),
            'ontology_version_id' => $version->id,
        ]);

        $this->audit->record(
            AuditEventType::OutcomeTermCreated,
            $request,
            $request->user(),
            resourceType: 'outcome_term',
            resourceId: $term->id,
        );

        return (new OutcomeTermResource($term))
            ->response()
            ->setStatusCode(201);
    }

    public function show(OutcomeTerm $outcomeTerm): OutcomeTermResource
    {
        $this->authorize('view', $outcomeTerm);

        return new OutcomeTermResource($outcomeTerm);
    }

    public function update(UpdateOutcomeTermRequest $request, OutcomeTerm $outcomeTerm): OutcomeTermResource
    {
        $outcomeTerm->update($request->safe()->only(['code', 'label', 'is_active', 'sort_order']));

        $this->audit->record(
            AuditEventType::OutcomeTermUpdated,
            $request,
            $request->user(),
            resourceType: 'outcome_term',
            resourceId: $outcomeTerm->id,
        );

        return new OutcomeTermResource($outcomeTerm->refresh());
    }

    public function destroy(Request $request, OutcomeTerm $outcomeTerm): Response|JsonResponse
    {
        $this->authorize('delete', $outcomeTerm);

        if ($outcomeTerm->isInUse()) {
            return response()->json([
                'message' => 'This Outcome term is in use and cannot be deleted. Deactivate it instead.',
                'code' => self::IN_USE_CODE,
            ], 409);
        }

        $termId = $outcomeTerm->id;
        $outcomeTerm->delete();

        $this->audit->record(
            AuditEventType::OutcomeTermDeleted,
            $request,
            $request->user(),
            resourceType: 'outcome_term',
            resourceId: $termId,
        );

        return response()->noContent();
    }
}
