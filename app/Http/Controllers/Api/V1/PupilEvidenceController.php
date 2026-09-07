<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceSource;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Pupils\Pupil;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListPupilEvidenceRequest;
use App\Http\Resources\Api\V1\EvidenceRecordResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PupilEvidenceController extends Controller
{
    public function index(ListPupilEvidenceRequest $request, Pupil $pupil): AnonymousResourceCollection
    {
        $query = EvidenceRecord::query()
            ->where('pupil_id', $pupil->id)
            ->where('lifecycle', EvidenceLifecycle::Submitted)
            ->with(['pupil', 'author', 'settingTerm', 'provisionTerm', 'relatedIntervention.provisionTerm']);

        $filter = $request->filter();

        if ($filter === 'import') {
            $query->where('source', EvidenceSource::Import);
        } elseif ($filter === 'review_note') {
            // Review notes ship in 4.3 — accept the chip and return an empty list until then.
            $query->where('type', 'review_note');
        } elseif (in_array($filter, EvidenceType::values(), true)) {
            $query->where('type', $filter);
        }

        $records = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        return EvidenceRecordResource::collection($records);
    }
}
