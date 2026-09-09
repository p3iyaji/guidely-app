<?php

namespace Database\Factories;

use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company().' School',
            'address' => fake()->streetAddress(),
            'postcode' => fake()->postcode(),
            'city' => fake()->city(),
            'county' => fake()->randomElement([
                'Greater Manchester',
                'West Yorkshire',
                'Kent',
                'Essex',
                'Hampshire',
            ]),
            'country' => 'United Kingdom',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
