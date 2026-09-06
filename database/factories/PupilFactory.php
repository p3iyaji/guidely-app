<?php

namespace Database\Factories;

use App\Domain\Ontology\NeedTerm;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SendStatus;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pupil>
 */
class PupilFactory extends Factory
{
    protected $model = Pupil::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'tenant_id' => fn (array $attributes): string => School::query()
                ->findOrFail($attributes['school_id'])
                ->tenant_id,
            'given_name' => fake()->firstName(),
            'family_name' => fake()->lastName(),
            'mis_key' => null,
            'date_of_birth' => fake()->optional()->date(),
            'year_group' => 'Year '.fake()->numberBetween(7, 11),
            'send_status' => SendStatus::Neither,
            'documentation_status' => DocumentationStatus::NotStarted,
            'primary_need_term_id' => null,
            'primary_need_notes' => null,
            'secondary_need_term_id' => null,
            'secondary_need_notes' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forSchool(School $school): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $school->tenant_id,
            'school_id' => $school->id,
        ]);
    }

    public function withMisKey(?string $misKey = null): static
    {
        return $this->state(fn (array $attributes) => [
            'mis_key' => $misKey ?? fake()->unique()->bothify('MIS-####'),
        ]);
    }

    public function senSupport(): static
    {
        return $this->state(fn (array $attributes) => [
            'send_status' => SendStatus::SenSupport,
        ]);
    }

    public function ehcp(): static
    {
        return $this->state(fn (array $attributes) => [
            'send_status' => SendStatus::Ehcp,
        ]);
    }

    public function withPrimaryNeed(NeedTerm $term, ?string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'primary_need_term_id' => $term->id,
            'primary_need_notes' => $notes,
        ]);
    }

    public function withSecondaryNeed(NeedTerm $term, ?string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'secondary_need_term_id' => $term->id,
            'secondary_need_notes' => $notes,
        ]);
    }

    /**
     * After creating, assign the Pupil to the given staff User.
     *
     * @param  array{class_label?: ?string, cohort_label?: ?string, source?: string}  $pivot
     */
    public function assignedTo(User $user, array $pivot = []): static
    {
        return $this->afterCreating(function (Pupil $pupil) use ($user, $pivot): void {
            $pupil->assignTo($user, $pivot);
        });
    }
}
