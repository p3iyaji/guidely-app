<?php

namespace App\Domain\Ontology;

use App\Domain\Pupils\Pupil;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Domain\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PublishLibraryToTenant
{
    public function __construct(private EnqueueSreReevaluation $enqueueSreReevaluation) {}

    public function handle(
        Tenant $tenant,
        ?OntologyVersion $ontologyVersion,
        ?RuleLibraryVersion $ruleLibraryVersion,
    ): PublishLibraryResult {
        if ($ontologyVersion === null && $ruleLibraryVersion === null) {
            throw new InvalidArgumentException(
                'PublishLibraryToTenant requires an Ontology version, a Rule Library version, or both.'
            );
        }

        [
            $ontologyPublished,
            $ruleLibraryPublished,
            $ontologyPinChanged,
            $ruleLibraryPinChanged,
            $ontologyNeedsReevaluation,
            $ruleLibraryNeedsReevaluation,
        ] = DB::transaction(
            function () use ($tenant, $ontologyVersion, $ruleLibraryVersion): array {
                $lockedTenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

                $ontologyPublished = $this->publishOntologyVersion($ontologyVersion);
                $ruleLibraryPublished = $this->publishRuleLibraryVersion($ruleLibraryVersion);

                $ontologyAlreadyPinned = $ontologyVersion !== null
                    && $lockedTenant->current_ontology_version_id === $ontologyVersion->id;
                $ruleLibraryAlreadyPinned = $ruleLibraryVersion !== null
                    && $lockedTenant->current_rule_library_version_id === $ruleLibraryVersion->id;

                $ontologyPinChanged = $ontologyVersion !== null && ! $ontologyAlreadyPinned;
                $ruleLibraryPinChanged = $ruleLibraryVersion !== null && ! $ruleLibraryAlreadyPinned;

                $pinUpdates = [];

                if ($ontologyPinChanged) {
                    $pinUpdates['current_ontology_version_id'] = $ontologyVersion->id;
                }

                if ($ruleLibraryPinChanged) {
                    $pinUpdates['current_rule_library_version_id'] = $ruleLibraryVersion->id;
                }

                if ($pinUpdates !== []) {
                    $lockedTenant->forceFill($pinUpdates)->save();
                }

                return [
                    $ontologyPublished,
                    $ruleLibraryPublished,
                    $ontologyPinChanged,
                    $ruleLibraryPinChanged,
                    $ontologyPinChanged || ($ontologyPublished && $ontologyAlreadyPinned),
                    $ruleLibraryPinChanged || ($ruleLibraryPublished && $ruleLibraryAlreadyPinned),
                ];
            }
        );

        $tenant->refresh();

        $queuedCount = 0;

        if ($ontologyNeedsReevaluation || $ruleLibraryNeedsReevaluation) {
            $reason = $this->reevaluationReason(
                $ontologyVersion !== null && $ruleLibraryVersion !== null,
                $ontologyNeedsReevaluation,
                $ruleLibraryNeedsReevaluation,
            );

            $pupils = Pupil::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->orderBy('id')
                ->get();

            foreach ($pupils as $pupil) {
                $this->enqueueSreReevaluation->handle($pupil, $reason, 'publish-library');
                $queuedCount++;
            }
        }

        return new PublishLibraryResult(
            ontologyPublished: $ontologyPublished,
            ruleLibraryPublished: $ruleLibraryPublished,
            ontologyPinChanged: $ontologyPinChanged,
            ruleLibraryPinChanged: $ruleLibraryPinChanged,
            queuedCount: $queuedCount,
        );
    }

    private function publishOntologyVersion(?OntologyVersion $ontologyVersion): bool
    {
        if ($ontologyVersion === null || $ontologyVersion->status === OntologyVersionStatus::Published) {
            return false;
        }

        $ontologyVersion->forceFill([
            'status' => OntologyVersionStatus::Published,
            'published_at' => now(),
        ])->save();

        return true;
    }

    private function publishRuleLibraryVersion(?RuleLibraryVersion $ruleLibraryVersion): bool
    {
        if ($ruleLibraryVersion === null || $ruleLibraryVersion->status === RuleLibraryVersionStatus::Published) {
            return false;
        }

        $ruleLibraryVersion->forceFill([
            'status' => RuleLibraryVersionStatus::Published,
            'published_at' => now(),
        ])->save();

        return true;
    }

    private function reevaluationReason(
        bool $bothLibraries,
        bool $ontologyNeedsReevaluation,
        bool $ruleLibraryNeedsReevaluation,
    ): string {
        if ($bothLibraries && ($ontologyNeedsReevaluation || $ruleLibraryNeedsReevaluation)) {
            return 'library_published';
        }

        if ($ontologyNeedsReevaluation) {
            return 'ontology_published';
        }

        return 'rule_library_published';
    }
}
