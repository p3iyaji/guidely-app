<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\Rule;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RuleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RuleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('create', EvidenceRecord::class);

        $rules = Rule::query()
            ->forTenant(CurrentTenant::require())
            ->orderBy('sort_order')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return RuleResource::collection($rules);
    }
}
