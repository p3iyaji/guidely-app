<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Pupils\Pupil;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNeedTermRequest;
use App\Http\Requests\Api\V1\UpdateNeedTermRequest;
use App\Http\Resources\Api\V1\NeedTermResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NeedTermController extends Controller
{
    public const IN_USE_CODE = 'need_term_in_use';

    public function __construct(private AuditWriter $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (
            $user === null
            || (
                ! $user->can('create', EvidenceRecord::class)
                && ! $user->can('create', Pupil::class)
                && ! $user->can('viewAny', NeedTerm::class)
            )
        ) {
            throw new AuthorizationException;
        }

        $canManage = $user->can('viewAny', NeedTerm::class);

        $terms = NeedTerm::query()
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

        return NeedTermResource::collection($terms);
    }

    public function store(StoreNeedTermRequest $request): JsonResponse
    {
        $version = app(EffectiveOntologyVersion::class)->resolve();

        abort_if($version === null, 422, 'No published Ontology version is available for this Tenant.');

        $term = NeedTerm::query()->create([
            ...$request->safe()->only(['code', 'label', 'is_active', 'sort_order']),
            'ontology_version_id' => $version->id,
        ]);

        $this->audit->record(
            AuditEventType::NeedTermCreated,
            $request,
            $request->user(),
            resourceType: 'need_term',
            resourceId: $term->id,
        );

        return (new NeedTermResource($term))
            ->response()
            ->setStatusCode(201);
    }

    public function show(NeedTerm $needTerm): NeedTermResource
    {
        $this->authorize('view', $needTerm);

        return new NeedTermResource($needTerm);
    }

    public function update(UpdateNeedTermRequest $request, NeedTerm $needTerm): NeedTermResource
    {
        $needTerm->update($request->safe()->only(['code', 'label', 'is_active', 'sort_order']));

        $this->audit->record(
            AuditEventType::NeedTermUpdated,
            $request,
            $request->user(),
            resourceType: 'need_term',
            resourceId: $needTerm->id,
        );

        return new NeedTermResource($needTerm->refresh());
    }

    public function destroy(Request $request, NeedTerm $needTerm): Response|JsonResponse
    {
        $this->authorize('delete', $needTerm);

        if ($needTerm->isInUse()) {
            return response()->json([
                'message' => 'This Need term is in use and cannot be deleted. Deactivate it instead.',
                'code' => self::IN_USE_CODE,
            ], 409);
        }

        $termId = $needTerm->id;
        $needTerm->delete();

        $this->audit->record(
            AuditEventType::NeedTermDeleted,
            $request,
            $request->user(),
            resourceType: 'need_term',
            resourceId: $termId,
        );

        return response()->noContent();
    }
}
