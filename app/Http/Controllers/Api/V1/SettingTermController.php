<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Pupils\Pupil;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSettingTermRequest;
use App\Http\Requests\Api\V1\UpdateSettingTermRequest;
use App\Http\Resources\Api\V1\SettingTermResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SettingTermController extends Controller
{
    public const IN_USE_CODE = 'setting_term_in_use';

    public function __construct(private AuditWriter $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (
            $user === null
            || (
                ! $user->can('create', EvidenceRecord::class)
                && ! $user->can('create', Pupil::class)
                && ! $user->can('viewAny', SettingTerm::class)
            )
        ) {
            throw new AuthorizationException;
        }

        $canManage = $user->can('viewAny', SettingTerm::class);

        $terms = SettingTerm::query()
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

        return SettingTermResource::collection($terms);
    }

    public function store(StoreSettingTermRequest $request): JsonResponse
    {
        $version = app(EffectiveOntologyVersion::class)->resolve();

        abort_if($version === null, 422, 'No published Ontology version is available for this Tenant.');

        $term = SettingTerm::query()->create([
            ...$request->safe()->only(['code', 'label', 'is_active', 'sort_order']),
            'ontology_version_id' => $version->id,
        ]);

        $this->audit->record(
            AuditEventType::SettingTermCreated,
            $request,
            $request->user(),
            resourceType: 'setting_term',
            resourceId: $term->id,
        );

        return (new SettingTermResource($term))
            ->response()
            ->setStatusCode(201);
    }

    public function show(SettingTerm $settingTerm): SettingTermResource
    {
        $this->authorize('view', $settingTerm);

        return new SettingTermResource($settingTerm);
    }

    public function update(UpdateSettingTermRequest $request, SettingTerm $settingTerm): SettingTermResource
    {
        $settingTerm->update($request->safe()->only(['code', 'label', 'is_active', 'sort_order']));

        $this->audit->record(
            AuditEventType::SettingTermUpdated,
            $request,
            $request->user(),
            resourceType: 'setting_term',
            resourceId: $settingTerm->id,
        );

        return new SettingTermResource($settingTerm->refresh());
    }

    public function destroy(Request $request, SettingTerm $settingTerm): Response|JsonResponse
    {
        $this->authorize('delete', $settingTerm);

        if ($settingTerm->isInUse()) {
            return response()->json([
                'message' => 'This Setting term is in use and cannot be deleted. Deactivate it instead.',
                'code' => self::IN_USE_CODE,
            ], 409);
        }

        $termId = $settingTerm->id;
        $settingTerm->delete();

        $this->audit->record(
            AuditEventType::SettingTermDeleted,
            $request,
            $request->user(),
            resourceType: 'setting_term',
            resourceId: $termId,
        );

        return response()->noContent();
    }
}
