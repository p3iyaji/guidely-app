<?php

namespace Database\Factories;

use App\Domain\Ontology\PilotRuleLibrary;
use App\Domain\Ontology\Rule;
use App\Domain\Ontology\RuleCategory;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Ontology\SreDimension;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rule>
 */
class RuleFactory extends Factory
{
    protected $model = Rule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_library_version_id' => fn (): string => PilotRuleLibrary::ensurePublishedVersion()->id,
            'code' => strtoupper(fake()->unique()->bothify('RULE-##')),
            'label' => fake()->unique()->words(4, true),
            'dimension' => fake()->randomElement(SreDimension::cases()),
            'category' => fake()->randomElement(RuleCategory::cases()),
            'condition' => [
                'all' => [
                    ['type' => 'evidence_present', 'min_count' => 1],
                ],
            ],
            'evaluation' => [
                'type' => 'documentation_check',
            ],
            'outcome' => [
                'result' => 'met',
            ],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function forVersion(RuleLibraryVersion $version): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_library_version_id' => $version->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
