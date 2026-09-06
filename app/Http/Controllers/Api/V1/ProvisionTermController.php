<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\ProvisionTerm;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProvisionTermResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProvisionTermController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('create', EvidenceRecord::class);

        $terms = ProvisionTerm::query()
            ->fromPublishedStub()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return ProvisionTermResource::collection($terms);
    }
}
