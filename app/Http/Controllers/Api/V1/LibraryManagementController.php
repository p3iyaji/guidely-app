<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\PublishLibraryResult;
use App\Domain\Ontology\PublishLibraryToTenant;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Tenancy\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTenantLibraryRequest;
use App\Http\Resources\Api\V1\LibraryVersionResource;
use App\Http\Resources\Api\V1\OperatorTenantLibraryResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LibraryManagementController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage-tenant-libraries');

        return response()->json([
            'data' => [
                'tenants' => OperatorTenantLibraryResource::collection($this->tenants())
                    ->resolve($request),
                'ontology_versions' => LibraryVersionResource::collection($this->ontologyVersions())
                    ->resolve($request),
                'rule_library_versions' => LibraryVersionResource::collection($this->ruleLibraryVersions())
                    ->resolve($request),
            ],
        ]);
    }

    public function update(
        UpdateTenantLibraryRequest $request,
        Tenant $tenant,
        PublishLibraryToTenant $publisher,
    ): JsonResponse {
        $validated = $request->validated();
        $ontologyVersion = isset($validated['ontology_version_id'])
            ? OntologyVersion::query()->findOrFail($validated['ontology_version_id'])
            : null;
        $ruleLibraryVersion = isset($validated['rule_library_version_id'])
            ? RuleLibraryVersion::query()->findOrFail($validated['rule_library_version_id'])
            : null;
        $priorOntologyPin = $tenant->current_ontology_version_id;
        $priorRuleLibraryPin = $tenant->current_rule_library_version_id;

        $result = $publisher->handle($tenant, $ontologyVersion, $ruleLibraryVersion);

        $this->audit->record(
            AuditEventType::LibraryPublished,
            $request,
            $request->user(),
            tenantId: $tenant->id,
            resourceType: 'tenant',
            resourceId: $tenant->id,
            metadata: $this->auditMetadata(
                $ontologyVersion,
                $ruleLibraryVersion,
                $priorOntologyPin,
                $priorRuleLibraryPin,
                $result,
            ),
        );

        $tenant->load(['currentOntologyVersion', 'currentRuleLibraryVersion']);

        return response()->json([
            'data' => [
                'tenant' => (new OperatorTenantLibraryResource($tenant))->resolve($request),
                'result' => [
                    'ontology_published' => $result->ontologyPublished,
                    'rule_library_published' => $result->ruleLibraryPublished,
                    'ontology_pin_changed' => $result->ontologyPinChanged,
                    'rule_library_pin_changed' => $result->ruleLibraryPinChanged,
                    'queued_count' => $result->queuedCount,
                ],
            ],
        ]);
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(): Collection
    {
        return Tenant::query()
            ->select([
                'id',
                'name',
                'type',
                'current_ontology_version_id',
                'current_rule_library_version_id',
            ])
            ->with([
                'currentOntologyVersion:id,code,label,status,published_at',
                'currentRuleLibraryVersion:id,code,label,status,published_at',
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, OntologyVersion>
     */
    private function ontologyVersions(): Collection
    {
        return OntologyVersion::query()
            ->select(['id', 'code', 'label', 'status', 'published_at'])
            ->orderBy('code')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, RuleLibraryVersion>
     */
    private function ruleLibraryVersions(): Collection
    {
        return RuleLibraryVersion::query()
            ->select(['id', 'code', 'label', 'status', 'published_at'])
            ->orderBy('code')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function auditMetadata(
        ?OntologyVersion $ontologyVersion,
        ?RuleLibraryVersion $ruleLibraryVersion,
        ?string $priorOntologyPin,
        ?string $priorRuleLibraryPin,
        PublishLibraryResult $result,
    ): array {
        $metadata = [
            'source' => 'operator-ui',
            'queued_count' => $result->queuedCount,
        ];

        if ($ontologyVersion !== null) {
            $metadata['ontology_code'] = $ontologyVersion->code;
            $metadata['ontology_version_id'] = $ontologyVersion->id;
            $metadata['prior_ontology_version_id'] = $priorOntologyPin;
            $metadata['new_ontology_version_id'] = $ontologyVersion->id;
            $metadata['ontology_published'] = $result->ontologyPublished;
            $metadata['ontology_pin_changed'] = $result->ontologyPinChanged;
        }

        if ($ruleLibraryVersion !== null) {
            $metadata['rule_library_code'] = $ruleLibraryVersion->code;
            $metadata['rule_library_version_id'] = $ruleLibraryVersion->id;
            $metadata['prior_rule_library_version_id'] = $priorRuleLibraryPin;
            $metadata['new_rule_library_version_id'] = $ruleLibraryVersion->id;
            $metadata['rule_library_published'] = $result->ruleLibraryPublished;
            $metadata['rule_library_pin_changed'] = $result->ruleLibraryPinChanged;
        }

        return $metadata;
    }
}
