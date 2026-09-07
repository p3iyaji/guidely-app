<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\PublishLibraryToTenant;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Tenancy\Tenant;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublishLibraryCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'guidely:publish-library
                            {tenant_id : Existing Tenant ULID}
                            {--ontology= : Ontology version code to publish and pin}
                            {--rule-library= : Rule Library version code to publish and pin}';

    /**
     * @var string
     */
    protected $description = 'Publish an Ontology and/or Rule Library version and pin it onto a Tenant, then enqueue SRE re-evaluation when the Tenant\'s effective library changes.';

    public function handle(PublishLibraryToTenant $publisher, AuditWriter $audit): int
    {
        $tenantId = (string) $this->argument('tenant_id');
        $ontologyCode = $this->optionalCode('ontology');
        $ruleLibraryCode = $this->optionalCode('rule-library');

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            $this->error("Tenant [{$tenantId}] was not found.");

            return self::FAILURE;
        }

        $validator = Validator::make(
            [
                'ontology' => $ontologyCode,
                'rule_library' => $ruleLibraryCode,
            ],
            [
                'ontology' => ['nullable', 'required_without:rule_library', 'string', 'exists:ontology_versions,code'],
                'rule_library' => ['nullable', 'required_without:ontology', 'string', 'exists:rule_library_versions,code'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $ontologyVersion = $ontologyCode === null
            ? null
            : OntologyVersion::query()->where('code', $ontologyCode)->first();
        $ruleLibraryVersion = $ruleLibraryCode === null
            ? null
            : RuleLibraryVersion::query()->where('code', $ruleLibraryCode)->first();

        if ($ontologyCode !== null && $ontologyVersion === null) {
            $this->error("Ontology version [{$ontologyCode}] was not found.");

            return self::FAILURE;
        }

        if ($ruleLibraryCode !== null && $ruleLibraryVersion === null) {
            $this->error("Rule Library version [{$ruleLibraryCode}] was not found.");

            return self::FAILURE;
        }

        $priorOntologyPin = $tenant->current_ontology_version_id;
        $priorRuleLibraryPin = $tenant->current_rule_library_version_id;

        $result = $publisher->handle($tenant, $ontologyVersion, $ruleLibraryVersion);

        $audit->record(
            AuditEventType::LibraryPublished,
            Request::create('/', 'CONSOLE'),
            tenantId: $tenant->id,
            resourceType: 'tenant',
            resourceId: $tenant->id,
            metadata: $this->auditMetadata(
                $ontologyVersion,
                $ruleLibraryVersion,
                $priorOntologyPin,
                $priorRuleLibraryPin,
                $result->ontologyPublished,
                $result->ruleLibraryPublished,
                $result->ontologyPinChanged,
                $result->ruleLibraryPinChanged,
                $result->queuedCount,
            ),
        );

        $this->info("Library publish completed for Tenant [{$tenant->id}].");
        $this->info("Queued {$result->queuedCount} SRE re-evaluations.");

        return self::SUCCESS;
    }

    private function optionalCode(string $option): ?string
    {
        $code = trim((string) ($this->option($option) ?? ''));

        return $code === '' ? null : $code;
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function auditMetadata(
        ?OntologyVersion $ontologyVersion,
        ?RuleLibraryVersion $ruleLibraryVersion,
        ?string $priorOntologyPin,
        ?string $priorRuleLibraryPin,
        bool $ontologyPublished,
        bool $ruleLibraryPublished,
        bool $ontologyPinChanged,
        bool $ruleLibraryPinChanged,
        int $queuedCount,
    ): array {
        $metadata = [
            'source' => 'guidely:publish-library',
            'queued_count' => $queuedCount,
        ];

        if ($ontologyVersion !== null) {
            $metadata['ontology_code'] = $ontologyVersion->code;
            $metadata['ontology_version_id'] = $ontologyVersion->id;
            $metadata['prior_ontology_version_id'] = $priorOntologyPin;
            $metadata['new_ontology_version_id'] = $ontologyVersion->id;
            $metadata['ontology_published'] = $ontologyPublished;
            $metadata['ontology_pin_changed'] = $ontologyPinChanged;
        }

        if ($ruleLibraryVersion !== null) {
            $metadata['rule_library_code'] = $ruleLibraryVersion->code;
            $metadata['rule_library_version_id'] = $ruleLibraryVersion->id;
            $metadata['prior_rule_library_version_id'] = $priorRuleLibraryPin;
            $metadata['new_rule_library_version_id'] = $ruleLibraryVersion->id;
            $metadata['rule_library_published'] = $ruleLibraryPublished;
            $metadata['rule_library_pin_changed'] = $ruleLibraryPinChanged;
        }

        return $metadata;
    }
}
