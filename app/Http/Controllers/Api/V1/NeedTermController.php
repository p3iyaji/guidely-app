<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NeedTermResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NeedTermController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (
            $user === null
            || (
                ! $user->can('create', EvidenceRecord::class)
                && ! $user->can('create', Pupil::class)
            )
        ) {
            throw new AuthorizationException;
        }

        $terms = NeedTerm::query()
            ->forTenant(CurrentTenant::require())
            ->orderBy('sort_order')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return NeedTermResource::collection($terms);
    }
}
