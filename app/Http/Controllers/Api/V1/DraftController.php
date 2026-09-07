<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitDraftRequest;
use App\Http\Requests\Api\V1\UpdateDraftRequest;
use App\Http\Resources\Api\V1\EvidenceRecordResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class DraftController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('listDrafts', EvidenceRecord::class);

        /** @var User $user */
        $user = $request->user();

        $query = EvidenceRecord::query()
            ->where('lifecycle', EvidenceLifecycle::Draft)
            ->with(['pupil', 'author', 'settingTerm', 'provisionTerm', 'relatedIntervention.provisionTerm'])
            ->whereHas('pupil');

        if ($user->role === Role::Senco) {
            $schoolIds = $user->schools()->pluck('schools.id');
            $query->whereHas('pupil', function ($pupilQuery) use ($schoolIds): void {
                $pupilQuery->whereIn('school_id', $schoolIds);
            });
        } else {
            $query->where('author_id', $user->id);
        }

        $drafts = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return EvidenceRecordResource::collection($drafts);
    }

    public function show(EvidenceRecord $draft): EvidenceRecordResource
    {
        abort_unless($draft->lifecycle === EvidenceLifecycle::Draft, 404);

        $this->authorize('view', $draft);

        $draft->load(['pupil', 'author', 'settingTerm', 'provisionTerm', 'relatedIntervention.provisionTerm']);

        return new EvidenceRecordResource($draft);
    }

    public function update(UpdateDraftRequest $request, EvidenceRecord $draft): EvidenceRecordResource
    {
        abort_unless($draft->lifecycle === EvidenceLifecycle::Draft, 404);

        $payload = $request->draftPayload();
        $pupilId = $payload['pupil_id'] ?? $draft->pupil_id;

        /** @var Pupil $pupil */
        $pupil = Pupil::query()->findOrFail($pupilId);

        $this->authorize('createForPupil', [EvidenceRecord::class, $pupil]);

        $record = DB::transaction(function () use ($request, $draft, $payload, $pupil): EvidenceRecord {
            $locked = $this->lockDraftOrConflict($draft);

            $attributes = [];

            if (array_key_exists('pupil_id', $payload)) {
                $attributes['pupil_id'] = $pupil->id;
            }

            if (array_key_exists('occurred_at', $payload)) {
                $attributes['occurred_at'] = $payload['occurred_at'];
            }

            if (array_key_exists('body', $payload)) {
                $attributes['body'] = $payload['body'];
            }

            if ($locked->type === EvidenceType::Observation) {
                if (array_key_exists('setting_term_id', $payload)) {
                    $attributes['setting_term_id'] = $payload['setting_term_id'];
                }
            } elseif ($locked->type === EvidenceType::Intervention) {
                if (array_key_exists('provision_term_id', $payload)) {
                    $attributes['provision_term_id'] = $payload['provision_term_id'];
                }
            } elseif (array_key_exists('related_intervention_id', $payload)) {
                $attributes['related_intervention_id'] = $payload['related_intervention_id'];
            }

            if ($attributes !== []) {
                $locked->fill($attributes)->save();
            }

            $locked->load(['pupil', 'author', 'settingTerm', 'provisionTerm', 'relatedIntervention.provisionTerm']);

            $this->audit->record(
                $this->updatedEventType($locked->type),
                $request,
                $request->user(),
                resourceType: 'evidence_record',
                resourceId: $locked->id,
                metadata: $this->auditMetadata($locked, $payload['client_type']),
            );

            return $locked;
        });

        return new EvidenceRecordResource($record);
    }

    public function submit(SubmitDraftRequest $request, EvidenceRecord $draft): EvidenceRecordResource
    {
        abort_unless($draft->lifecycle === EvidenceLifecycle::Draft, 404);

        $payload = $request->submitPayload();

        /** @var Pupil $pupil */
        $pupil = Pupil::query()->findOrFail($payload['pupil_id']);

        $this->authorize('createForPupil', [EvidenceRecord::class, $pupil]);

        $record = DB::transaction(function () use ($request, $draft, $payload, $pupil): EvidenceRecord {
            $locked = $this->lockDraftOrConflict($draft);

            $attributes = [
                'pupil_id' => $pupil->id,
                'occurred_at' => $payload['occurred_at'],
                'body' => $payload['body'],
            ];

            if ($locked->type === EvidenceType::Observation) {
                $attributes['setting_term_id'] = $payload['setting_term_id'];
                $attributes['provision_term_id'] = null;
                $attributes['related_intervention_id'] = null;
            } elseif ($locked->type === EvidenceType::Intervention) {
                $attributes['provision_term_id'] = $payload['provision_term_id'];
                $attributes['setting_term_id'] = null;
                $attributes['related_intervention_id'] = null;
            } else {
                $attributes['related_intervention_id'] = $payload['related_intervention_id'];
                $attributes['setting_term_id'] = null;
                $attributes['provision_term_id'] = null;
            }

            $locked->fill($attributes);
            $locked->forceFill([
                'lifecycle' => EvidenceLifecycle::Submitted,
            ])->save();

            $locked->load(['pupil', 'author', 'settingTerm', 'provisionTerm', 'relatedIntervention.provisionTerm']);

            $this->audit->record(
                $this->submittedEventType($locked->type),
                $request,
                $request->user(),
                resourceType: 'evidence_record',
                resourceId: $locked->id,
                metadata: $this->auditMetadata($locked, $payload['client_type']),
            );

            return $locked;
        });

        $this->enqueueSreReevaluation($pupil);

        return new EvidenceRecordResource($record);
    }

    private function lockDraftOrConflict(EvidenceRecord $draft): EvidenceRecord
    {
        $locked = EvidenceRecord::query()
            ->whereKey($draft->id)
            ->where('lifecycle', EvidenceLifecycle::Draft)
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw new HttpException(409, 'This draft is no longer available to update or submit.');
        }

        return $locked;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditMetadata(EvidenceRecord $record, string $clientType): array
    {
        $metadata = [
            'client_type' => $clientType,
            'pupil_id' => $record->pupil_id,
            'type' => $record->type->value,
            'lifecycle' => $record->lifecycle->value,
            'occurred_at' => $record->occurred_at?->utc()->toIso8601String(),
        ];

        if ($record->type === EvidenceType::Observation) {
            $metadata['setting_term_id'] = $record->setting_term_id;
            $metadata['setting_term_code'] = $record->settingTerm?->code;
        } elseif ($record->type === EvidenceType::Intervention) {
            $metadata['provision_term_id'] = $record->provision_term_id;
            $metadata['provision_term_code'] = $record->provisionTerm?->code;
        } else {
            $metadata['related_intervention_id'] = $record->related_intervention_id;
        }

        return $metadata;
    }

    private function updatedEventType(EvidenceType $type): AuditEventType
    {
        return match ($type) {
            EvidenceType::Observation => AuditEventType::EvidenceObservationUpdated,
            EvidenceType::Intervention => AuditEventType::EvidenceInterventionUpdated,
            EvidenceType::Response => AuditEventType::EvidenceResponseUpdated,
        };
    }

    private function submittedEventType(EvidenceType $type): AuditEventType
    {
        return match ($type) {
            EvidenceType::Observation => AuditEventType::EvidenceObservationSubmitted,
            EvidenceType::Intervention => AuditEventType::EvidenceInterventionSubmitted,
            EvidenceType::Response => AuditEventType::EvidenceResponseSubmitted,
        };
    }

    private function enqueueSreReevaluation(Pupil $pupil): void
    {
        $jobClass = 'App\\Jobs\\SreReevaluatePupil';

        if (! class_exists($jobClass)) {
            return;
        }

        try {
            dispatch(new $jobClass($pupil->tenant_id, $pupil->id, 'evidence_submitted'));
        } catch (Throwable $e) {
            Log::warning('draft.sre_dispatch_failed', [
                'tenant_id' => $pupil->tenant_id,
                'pupil_id' => $pupil->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
