<?php

namespace Database\Factories;

use App\Domain\Reporting\ComplianceAlert;
use App\Domain\Reporting\ComplianceAlertMetric;
use App\Domain\Reporting\ComplianceAlertScope;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceAlert>
 */
class ComplianceAlertFactory extends Factory
{
    protected $model = ComplianceAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'scope' => ComplianceAlertScope::Trust,
            'school_id' => null,
            'metric' => ComplianceAlertMetric::GapDensity,
            'threshold_value' => 0.5,
            'observed_value' => 0.5,
            'is_open' => true,
            'resolved_at' => null,
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
            'scope' => ComplianceAlertScope::School,
            'school_id' => $school->id,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_open' => false,
            'resolved_at' => now(),
        ]);
    }
}
