<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceRecordVersion;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AmendEvidenceRequest;
use App\Http\Resources\Api\V1\EvidenceRecordResource;
use App\Http\Resources\Api\V1\EvidenceRecordVersionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EvidenceController extends Controller
{
    public function __construct(
        private AuditWriter $audit,
        private EnqueueSreReevaluation $enqueueSreReevaluation,
    ) {}

    public function update(AmendEvidenceRequest $request, EvidenceRecord $evidence): EvidenceRecordResource
    {
        abort_unless($evidence->lifecycle === EvidenceLifecycle::Submitted, 403);
        abort_if($evidence->type === EvidenceType::ReviewNote, 403);

        $payload = $request->amendPayload();

        /** @var Pupil $pupil */
        $pupil = Pupil::query()->findOrFail($evidence->pupil_id);

        $record = DB::transaction(function () use ($request, $evidence, $payload): EvidenceRecord {
            $locked = $this->lockSubmittedOrConflict($evidence);

            abort_if($locked->type === EvidenceType::ReviewNote, 403);

            $this->appendPriorVersion($locked, $request->user()->id);

            $attributes = [
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
            } elseif ($locked->type === EvidenceType::Response) {
                $attributes['related_intervention_id'] = $payload['related_intervention_id'];
                $attributes['setting_term_id'] = null;
                $attributes['provision_term_id'] = null;
            } else {
                abort(403);
            }

            $locked->fill($attributes)->save();

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

        $this->enqueueSreReevaluation->handle($pupil, 'evidence_amended', 'evidence.amend');

        return new EvidenceRecordResource($record);
    }

    public function versions(EvidenceRecord $evidence): AnonymousResourceCollection
    {
        $this->authorize('view', $evidence);

        $versions = $evidence->versions()
            ->with('supersededByUser')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->get();

        return EvidenceRecordVersionResource::collection($versions);
    }

    private function lockSubmittedOrConflict(EvidenceRecord $evidence): EvidenceRecord
    {
        $locked = EvidenceRecord::query()
            ->whereKey($evidence->id)
            ->where('lifecycle', EvidenceLifecycle::Submitted)
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw new HttpException(409, 'This Evidence Record is no longer available to amend.');
        }

        return $locked;
    }

    private function appendPriorVersion(EvidenceRecord $record, string $supersededBy): void
    {
        $nextVersion = ((int) $record->versions()->max('version')) + 1;

        $version = new EvidenceRecordVersion([
            'evidence_record_id' => $record->id,
            'version' => $nextVersion,
            'snapshot' => [
                'type' => $record->type->value,
                'author_id' => $record->author_id,
                'occurred_at' => $record->occurred_at?->utc()->toIso8601String(),
                'setting_term_id' => $record->setting_term_id,
                'provision_term_id' => $record->provision_term_id,
                'related_intervention_id' => $record->related_intervention_id,
                'body' => $record->body,
            ],
            'superseded_at' => now()->utc(),
            'superseded_by' => $supersededBy,
        ]);
        $version->forceFill([
            'tenant_id' => $record->tenant_id,
        ])->save();
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
            'reason' => 'evidence_amended',
        ];

        if ($record->type === EvidenceType::Observation) {
            $metadata['setting_term_id'] = $record->setting_term_id;
            $metadata['setting_term_code'] = $record->settingTerm?->code;
        } elseif ($record->type === EvidenceType::Intervention) {
            $metadata['provision_term_id'] = $record->provision_term_id;
            $metadata['provision_term_code'] = $record->provisionTerm?->code;
        } elseif ($record->type === EvidenceType::Response) {
            $metadata['related_intervention_id'] = $record->related_intervention_id;
        } else {
            abort(403);
        }

        return $metadata;
    }

    private function updatedEventType(EvidenceType $type): AuditEventType
    {
        return match ($type) {
            EvidenceType::Observation => AuditEventType::EvidenceObservationUpdated,
            EvidenceType::Intervention => AuditEventType::EvidenceInterventionUpdated,
            EvidenceType::Response => AuditEventType::EvidenceResponseUpdated,
            EvidenceType::ReviewNote => abort(403),
        };
    }
}
