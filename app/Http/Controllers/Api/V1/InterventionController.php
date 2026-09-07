<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceSource;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInterventionRequest;
use App\Http\Resources\Api\V1\EvidenceRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InterventionController extends Controller
{
    public function __construct(
        private AuditWriter $audit,
        private EnqueueSreReevaluation $enqueueSreReevaluation,
    ) {}

    public function store(StoreInterventionRequest $request): JsonResponse
    {
        $payload = $request->interventionPayload();
        $lifecycle = $payload['lifecycle'] === 'draft'
            ? EvidenceLifecycle::Draft
            : EvidenceLifecycle::Submitted;

        /** @var Pupil $pupil */
        $pupil = Pupil::query()->findOrFail($payload['pupil_id']);

        $this->authorize('createForPupil', [EvidenceRecord::class, $pupil]);

        $record = DB::transaction(function () use ($request, $payload, $pupil, $lifecycle): EvidenceRecord {
            $record = new EvidenceRecord([
                'pupil_id' => $pupil->id,
                'author_id' => $request->user()->id,
                'occurred_at' => $payload['occurred_at'],
                'provision_term_id' => $payload['provision_term_id'],
                'body' => $payload['body'],
                'source' => EvidenceSource::Capture,
            ]);
            $record->forceFill([
                'type' => EvidenceType::Intervention,
                'lifecycle' => $lifecycle,
            ])->save();

            $record->load(['provisionTerm', 'pupil']);

            $this->audit->record(
                AuditEventType::EvidenceInterventionCreated,
                $request,
                $request->user(),
                resourceType: 'evidence_record',
                resourceId: $record->id,
                metadata: [
                    'client_type' => $payload['client_type'],
                    'pupil_id' => $record->pupil_id,
                    'type' => $record->type->value,
                    'lifecycle' => $record->lifecycle->value,
                    'provision_term_id' => $record->provision_term_id,
                    'provision_term_code' => $this->termCode($record->provisionTerm),
                    'occurred_at' => $record->occurred_at?->utc()->toIso8601String(),
                ],
            );

            return $record;
        });

        if ($lifecycle === EvidenceLifecycle::Submitted) {
            $this->enqueueSreReevaluation->handle($pupil, 'evidence_submitted', 'intervention');
        }

        return (new EvidenceRecordResource($record))
            ->response()
            ->setStatusCode(201);
    }

    private function termCode(?ProvisionTerm $term): ?string
    {
        return $term?->code;
    }
}
