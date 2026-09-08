<?php

namespace Database\Factories;

use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SafeguardingSeverity;
use App\Domain\Pupils\SafeguardingSignal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafeguardingSignal>
 */
class SafeguardingSignalFactory extends Factory
{
    protected $model = SafeguardingSignal::class;

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
            'present' => true,
            'severity' => SafeguardingSeverity::Medium,
        ];
    }

    public function forPupil(Pupil $pupil): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $pupil->tenant_id,
            'pupil_id' => $pupil->id,
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'present' => false,
            'severity' => null,
        ]);
    }
}
