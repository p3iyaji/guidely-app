<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Pupils\Pupil;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreResponseRequest;
use App\Http\Resources\Api\V1\EvidenceRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResponseController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    public function store(StoreResponseRequest $request): JsonResponse
    {
        $payload = $request->responsePayload();
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
                'related_intervention_id' => $payload['related_intervention_id'],
                'body' => $payload['body'],
            ]);
            $record->forceFill([
                'type' => EvidenceType::Response,
                'lifecycle' => $lifecycle,
            ])->save();

            $record->load(['relatedIntervention.provisionTerm', 'pupil']);

            $this->audit->record(
                AuditEventType::EvidenceResponseCreated,
                $request,
                $request->user(),
                resourceType: 'evidence_record',
                resourceId: $record->id,
                metadata: [
                    'client_type' => $payload['client_type'],
                    'pupil_id' => $record->pupil_id,
                    'type' => $record->type->value,
                    'lifecycle' => $record->lifecycle->value,
                    'related_intervention_id' => $record->related_intervention_id,
                    'occurred_at' => $record->occurred_at?->utc()->toIso8601String(),
                ],
            );

            return $record;
        });

        if ($lifecycle === EvidenceLifecycle::Submitted) {
            $this->enqueueSreReevaluation($pupil);
        }

        return (new EvidenceRecordResource($record))
            ->response()
            ->setStatusCode(201);
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
            Log::warning('response.sre_dispatch_failed', [
                'tenant_id' => $pupil->tenant_id,
                'pupil_id' => $pupil->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
