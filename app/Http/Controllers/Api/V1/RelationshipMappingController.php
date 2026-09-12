<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\OutcomeTerm;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Ontology\RelationshipMapping;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Ontology\ThresholdTerm;
use App\Domain\Pupils\Pupil;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRelationshipMappingRequest;
use App\Http\Requests\Api\V1\UpdateRelationshipMappingRequest;
use App\Http\Resources\Api\V1\RelationshipMappingResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RelationshipMappingController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (
            $user === null
            || (
                ! $user->can('create', EvidenceRecord::class)
                && ! $user->can('create', Pupil::class)
                && ! $user->can('viewAny', RelationshipMapping::class)
            )
        ) {
            throw new AuthorizationException;
        }

        $canManage = $user->can('viewAny', RelationshipMapping::class);

        $mappings = RelationshipMapping::query()
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

        return RelationshipMappingResource::collection(
            $this->withTermLabels($mappings),
        );
    }

    public function store(StoreRelationshipMappingRequest $request): JsonResponse
    {
        $version = app(EffectiveOntologyVersion::class)->resolve();

        abort_if($version === null, 422, 'No published Ontology version is available for this Tenant.');

        $mapping = RelationshipMapping::query()->create([
            ...$request->safe()->only(['code', 'label', 'relationship_type', 'from_domain', 'from_term_id', 'to_domain', 'to_term_id', 'is_active', 'sort_order']),
            'ontology_version_id' => $version->id,
        ]);

        $this->audit->record(
            AuditEventType::RelationshipMappingCreated,
            $request,
            $request->user(),
            resourceType: 'relationship_mapping',
            resourceId: $mapping->id,
        );

        return (new RelationshipMappingResource($mapping))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RelationshipMapping $relationshipMapping): RelationshipMappingResource
    {
        $this->authorize('view', $relationshipMapping);

        return new RelationshipMappingResource(
            $this->withTermLabels(collect([$relationshipMapping]))->first(),
        );
    }

    public function update(UpdateRelationshipMappingRequest $request, RelationshipMapping $relationshipMapping): RelationshipMappingResource
    {
        $relationshipMapping->update($request->safe()->only(['code', 'label', 'relationship_type', 'from_domain', 'from_term_id', 'to_domain', 'to_term_id', 'is_active', 'sort_order']));

        $this->audit->record(
            AuditEventType::RelationshipMappingUpdated,
            $request,
            $request->user(),
            resourceType: 'relationship_mapping',
            resourceId: $relationshipMapping->id,
        );

        return new RelationshipMappingResource($relationshipMapping->refresh());
    }

    public function destroy(Request $request, RelationshipMapping $relationshipMapping): Response
    {
        $this->authorize('delete', $relationshipMapping);

        $mappingId = $relationshipMapping->id;
        $relationshipMapping->delete();

        $this->audit->record(
            AuditEventType::RelationshipMappingDeleted,
            $request,
            $request->user(),
            resourceType: 'relationship_mapping',
            resourceId: $mappingId,
        );

        return response()->noContent();
    }

    /**
     * Attach from/to term labels to mappings for the API resource.
     */
    private function withTermLabels($mappings): \Illuminate\Support\Collection
    {
        $termModels = [
            'need' => NeedTerm::class,
            'setting' => SettingTerm::class,
            'provision' => ProvisionTerm::class,
            'outcome' => OutcomeTerm::class,
            'threshold' => ThresholdTerm::class,
        ];
        $versionIds = $mappings->pluck('ontology_version_id')->unique()->values();
        $labelsByDomain = [];

        foreach ($termModels as $domain => $termModel) {
            $termIds = $mappings
                ->flatMap(fn (RelationshipMapping $mapping): array => [
                    $mapping->from_domain === $domain ? $mapping->from_term_id : null,
                    $mapping->to_domain === $domain ? $mapping->to_term_id : null,
                ])
                ->filter()
                ->unique()
                ->values();

            $labelsByDomain[$domain] = $termIds->isEmpty()
                ? collect()
                : $termModel::query()
                    ->whereIn('ontology_version_id', $versionIds)
                    ->whereIn('id', $termIds)
                    ->pluck('label', 'id');
        }

        foreach ($mappings as $mapping) {
            $mapping->setAttribute(
                'from_term_label',
                ($labelsByDomain[$mapping->from_domain] ?? collect())->get($mapping->from_term_id),
            );
            $mapping->setAttribute(
                'to_term_label',
                ($labelsByDomain[$mapping->to_domain] ?? collect())->get($mapping->to_term_id),
            );
        }

        return $mappings;
    }
}
