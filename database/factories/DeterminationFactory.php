<?php

namespace Database\Factories;

use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\PilotOntology;
use App\Domain\Ontology\PilotRuleLibrary;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Determination>
 *
 * Citation FKs are not mass-assignable on Determination; Factory::create uses
 * Model::unguarded so ontology_version_id / rule_library_version_id can still be set here.
 */
class DeterminationFactory extends Factory
{
    protected $model = Determination::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'pupil_id' => null,
            'ontology_version_id' => fn (): string => PilotOntology::ensurePublishedVersion()->id,
            'rule_library_version_id' => fn (): string => PilotRuleLibrary::ensurePublishedVersion()->id,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forPupil(Pupil $pupil): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $pupil->tenant_id,
            'pupil_id' => $pupil->id,
        ]);
    }

    public function forOntologyVersion(OntologyVersion $version): static
    {
        return $this->state(fn (array $attributes) => [
            'ontology_version_id' => $version->id,
        ]);
    }

    public function forRuleLibraryVersion(RuleLibraryVersion $version): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_library_version_id' => $version->id,
        ]);
    }
}
