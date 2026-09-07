<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Pupils\Pupil;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InterventionSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PupilInterventionController extends Controller
{
    public function index(Pupil $pupil): AnonymousResourceCollection
    {
        $this->authorize('createForPupil', [EvidenceRecord::class, $pupil]);

        $interventions = EvidenceRecord::query()
            ->where('pupil_id', $pupil->id)
            ->where('type', EvidenceType::Intervention)
            ->where('lifecycle', EvidenceLifecycle::Submitted)
            ->with(['provisionTerm'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return InterventionSummaryResource::collection($interventions);
    }
}
