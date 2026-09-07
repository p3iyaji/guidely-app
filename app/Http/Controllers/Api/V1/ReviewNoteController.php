<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceSource;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReviewNoteRequest;
use App\Http\Resources\Api\V1\EvidenceRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReviewNoteController extends Controller
{
    public function __construct(
        private AuditWriter $audit,
        private EnqueueSreReevaluation $enqueueSreReevaluation,
    ) {}

    public function store(StoreReviewNoteRequest $request): JsonResponse
    {
        $payload = $request->reviewNotePayload();

        /** @var Pupil $pupil */
        $pupil = Pupil::query()->findOrFail($payload['pupil_id']);

        $this->authorize('createReviewNote', [EvidenceRecord::class, $pupil]);

        $record = DB::transaction(function () use ($request, $payload, $pupil): EvidenceRecord {
            $record = new EvidenceRecord([
                'pupil_id' => $pupil->id,
                'author_id' => $request->user()->id,
                'occurred_at' => $payload['occurred_at'],
                'body' => $payload['body'],
                'source' => EvidenceSource::Capture,
            ]);
            $record->forceFill([
                'type' => EvidenceType::ReviewNote,
                'lifecycle' => EvidenceLifecycle::Submitted,
            ])->save();

            $record->load(['pupil', 'author']);

            $this->audit->record(
                AuditEventType::EvidenceReviewNoteCreated,
                $request,
                $request->user(),
                resourceType: 'evidence_record',
                resourceId: $record->id,
                metadata: [
                    'client_type' => $payload['client_type'],
                    'pupil_id' => $record->pupil_id,
                    'type' => $record->type->value,
                    'lifecycle' => $record->lifecycle->value,
                    'occurred_at' => $record->occurred_at?->utc()->toIso8601String(),
                ],
            );

            return $record;
        });

        $this->enqueueSreReevaluation->handle($pupil, 'evidence_submitted', 'review_note');

        return (new EvidenceRecordResource($record))
            ->response()
            ->setStatusCode(201);
    }
}
