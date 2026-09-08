<?php

namespace Database\Factories;

use App\Domain\Connectors\Connector;
use App\Domain\Connectors\ConnectorType;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends Factory<Connector>
 *
 * Writer fields are not mass-assignable on Connector;
 * Factory::create uses Model::unguarded so they can still be set here.
 */
class ConnectorFactory extends Factory
{
    protected $model = Connector::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => ConnectorType::PilotStub,
            'enabled' => false,
            'secret' => null,
            'field_shares' => [],
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => true,
        ]);
    }

    public function withSecret(string $plaintext = 'pilot-secret'): static
    {
        return $this->state(fn (array $attributes) => [
            'secret' => Crypt::encryptString($plaintext),
        ]);
    }
}
