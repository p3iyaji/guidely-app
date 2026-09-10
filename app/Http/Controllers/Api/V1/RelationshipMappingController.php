<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\OutcomeTerm;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Ontology\RelationshipMapping;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Ontology\ThresholdTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RelationshipMappingResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RelationshipMappingController extends Controller
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

        $mappings = RelationshipMapping::query()
            ->forTenant(CurrentTenant::require())
            ->orderBy('sort_order')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        $termModels = [
            'need' => NeedTerm::class,
            'setting' => SettingTerm::class,
            'provision' => ProvisionTerm::class,
            'outcome' => OutcomeTerm::class,
            'threshold' => ThresholdTerm::class,
        ];
        $versionIds = $mappings->pluck('ontology_version_id')->unique()->values();
        $labelsByDomain = [];

        foreach ($termModels as $domain => $termModel) {
            $termIds = $mappings
                ->flatMap(fn (RelationshipMapping $mapping): array => [
                    $mapping->from_domain === $domain ? $mapping->from_term_id : null,
                    $mapping->to_domain === $domain ? $mapping->to_term_id : null,
                ])
                ->filter()
                ->unique()
                ->values();

            $labelsByDomain[$domain] = $termIds->isEmpty()
                ? collect()
                : $termModel::query()
                    ->whereIn('ontology_version_id', $versionIds)
                    ->whereIn('id', $termIds)
                    ->pluck('label', 'id');
        }

        foreach ($mappings as $mapping) {
            $mapping->setAttribute(
                'from_term_label',
                ($labelsByDomain[$mapping->from_domain] ?? collect())->get($mapping->from_term_id),
            );
            $mapping->setAttribute(
                'to_term_label',
                ($labelsByDomain[$mapping->to_domain] ?? collect())->get($mapping->to_term_id),
            );
        }

        return RelationshipMappingResource::collection($mappings);
    }
}
