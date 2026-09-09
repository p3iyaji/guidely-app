<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\ProvisionTerm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProvisionTermRequest;
use App\Http\Requests\Api\V1\UpdateProvisionTermRequest;
use App\Http\Resources\Api\V1\ProvisionTermResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProvisionTermController extends Controller
{
    public const IN_USE_CODE = 'provision_term_in_use';

    public function __construct(private AuditWriter $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (
            $user === null
            || (
                ! $user->can('create', EvidenceRecord::class)
                && ! $user->can('viewAny', ProvisionTerm::class)
            )
        ) {
            throw new AuthorizationException;
        }

        $canManage = $user->can('viewAny', ProvisionTerm::class);

        $terms = ProvisionTerm::query()
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

        return ProvisionTermResource::collection($terms);
    }

    public function store(StoreProvisionTermRequest $request): JsonResponse
    {
        $version = app(EffectiveOntologyVersion::class)->resolve();

        abort_if($version === null, 422, 'No published Ontology version is available for this Tenant.');

        $term = ProvisionTerm::query()->create([
            ...$request->safe()->only(['code', 'label', 'is_active', 'sort_order']),
            'ontology_version_id' => $version->id,
        ]);

        $this->audit->record(
            AuditEventType::ProvisionTermCreated,
            $request,
            $request->user(),
            resourceType: 'provision_term',
            resourceId: $term->id,
        );

        return (new ProvisionTermResource($term))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProvisionTerm $provisionTerm): ProvisionTermResource
    {
        $this->authorize('view', $provisionTerm);

        return new ProvisionTermResource($provisionTerm);
    }

    public function update(UpdateProvisionTermRequest $request, ProvisionTerm $provisionTerm): ProvisionTermResource
    {
        $provisionTerm->update($request->safe()->only(['code', 'label', 'is_active', 'sort_order']));

        $this->audit->record(
            AuditEventType::ProvisionTermUpdated,
            $request,
            $request->user(),
            resourceType: 'provision_term',
            resourceId: $provisionTerm->id,
        );

        return new ProvisionTermResource($provisionTerm->refresh());
    }

    public function destroy(Request $request, ProvisionTerm $provisionTerm): Response|JsonResponse
    {
        $this->authorize('delete', $provisionTerm);

        if ($provisionTerm->isInUse()) {
            return response()->json([
                'message' => 'This Provision term is in use and cannot be deleted. Deactivate it instead.',
                'code' => self::IN_USE_CODE,
            ], 409);
        }

        $termId = $provisionTerm->id;
        $provisionTerm->delete();

        $this->audit->record(
            AuditEventType::ProvisionTermDeleted,
            $request,
            $request->user(),
            resourceType: 'provision_term',
            resourceId: $termId,
        );

        return response()->noContent();
    }
}
