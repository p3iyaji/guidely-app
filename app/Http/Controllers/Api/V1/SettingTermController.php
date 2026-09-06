<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\SettingTerm;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SettingTermResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettingTermController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('create', EvidenceRecord::class);

        $terms = SettingTerm::query()
            ->fromPublishedStub()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return SettingTermResource::collection($terms);
    }
}
