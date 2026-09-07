<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\OutcomeTerm;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OutcomeTermResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OutcomeTermController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('create', EvidenceRecord::class);

        $terms = OutcomeTerm::query()
            ->forTenant(CurrentTenant::require())
            ->orderBy('sort_order')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return OutcomeTermResource::collection($terms);
    }
}
