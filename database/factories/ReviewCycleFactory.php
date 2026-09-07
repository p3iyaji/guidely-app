<?php

namespace Database\Factories;

use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Reviews\ReviewCycleStatus;
use App\Domain\Reviews\ReviewCycleType;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewCycle>
 *
 * Writer fields are not mass-assignable on ReviewCycle;
 * Factory::create uses Model::unguarded so they can still be set here.
 */
class ReviewCycleFactory extends Factory
{
    protected $model = ReviewCycle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pupil_id' => Pupil::factory(),
            'tenant_id' => fn (array $attributes): string => Pupil::query()
                ->findOrFail($attributes['pupil_id'])
                ->tenant_id,
            'type' => ReviewCycleType::AnnualReview,
            'due_on' => now('Europe/London')->toDateString(),
            'ehcp_linked' => false,
            'status' => ReviewCycleStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
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

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewCycleStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewCycleStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    public function annualReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ReviewCycleType::AnnualReview,
        ]);
    }

    public function dueOn(string $dueOn): static
    {
        return $this->state(fn (array $attributes) => [
            'due_on' => $dueOn,
        ]);
    }
}
