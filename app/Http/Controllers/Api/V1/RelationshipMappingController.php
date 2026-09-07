<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\RelationshipMapping;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RelationshipMappingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RelationshipMappingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('create', EvidenceRecord::class);

        $terms = RelationshipMapping::query()
            ->forTenant(CurrentTenant::require())
            ->orderBy('sort_order')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return RelationshipMappingResource::collection($terms);
    }
}
