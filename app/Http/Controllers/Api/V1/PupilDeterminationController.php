<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DeterminationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PupilDeterminationController extends Controller
{
    /**
     * List Determinations for a Pupil (current when ?current=1).
     */
    public function index(Request $request, Pupil $pupil): AnonymousResourceCollection
    {
        $this->authorize('listForPupil', [Determination::class, $pupil]);

        $query = Determination::query()
            ->forPupil($pupil->id)
            ->with(['rule', 'ruleLibraryVersion'])
            ->orderBy('dimension')
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id');

        if ($request->boolean('current')) {
            $query->current();
        }

        return DeterminationResource::collection($query->get());
    }
}
