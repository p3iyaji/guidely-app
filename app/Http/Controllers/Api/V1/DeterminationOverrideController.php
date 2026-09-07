<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Domain\Sre\Override;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDeterminationOverrideRequest;
use App\Http\Resources\Api\V1\OverrideResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DeterminationOverrideController extends Controller
{
    public function __construct(
        private AuditWriter $audit,
        private EnqueueSreReevaluation $enqueueSreReevaluation,
    ) {}

    public function store(StoreDeterminationOverrideRequest $request, Determination $determination): JsonResponse
    {
        $this->authorize('override', $determination);

        /** @var Pupil $pupil */
        $pupil = $determination->relationLoaded('pupil') && $determination->pupil !== null
            ? $determination->pupil
            : Pupil::query()->findOrFail($determination->pupil_id);

        $override = DB::transaction(function () use ($request, $determination): Override {
            $override = new Override([
                'tenant_id' => $determination->tenant_id,
                'pupil_id' => $determination->pupil_id,
            ]);
            $override->forceFill([
                'determination_id' => $determination->id,
                'dimension' => $determination->dimension,
                'user_id' => $request->user()->id,
                'rationale' => $request->rationale(),
            ])->save();

            $this->audit->record(
                AuditEventType::SreOverrideCreated,
                $request,
                $request->user(),
                resourceType: 'override',
                resourceId: $override->id,
                metadata: [
                    'pupil_id' => $determination->pupil_id,
                    'determination_id' => $determination->id,
                    'dimension' => $determination->dimension instanceof SreDimension
                        ? $determination->dimension->value
                        : (string) $determination->dimension,
                ],
            );

            return $override;
        });

        $this->enqueueSreReevaluation->handle($pupil, 'override', 'override');

        return (new OverrideResource($override))
            ->response()
            ->setStatusCode(201);
    }
}
