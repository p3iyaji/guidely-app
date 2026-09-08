<?php

namespace Database\Factories;

use App\Domain\Reporting\TrustIndicatorSnapshot;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrustIndicatorSnapshot>
 */
class TrustIndicatorSnapshotFactory extends Factory
{
    protected $model = TrustIndicatorSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'school_id' => null,
            'month' => '2026-08',
            'snapshot_key' => '2026-08:trust',
            'pupils_in_scope' => 0,
            'gaps' => 0,
            'gap_density' => 0.0,
            'lateness_rate' => 0.0,
            'overdue_open_cycles' => 0,
            'open_cycles' => 0,
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
            'snapshot_key' => ($attributes['month'] ?? '2026-08').':'.$school->id,
        ]);
    }

    public function forMonth(string $month): static
    {
        return $this->state(function (array $attributes) use ($month): array {
            $schoolId = $attributes['school_id'] ?? null;

            return [
                'month' => $month,
                'snapshot_key' => $month.':'.(is_string($schoolId) && $schoolId !== '' ? $schoolId : 'trust'),
            ];
        });
    }
}
