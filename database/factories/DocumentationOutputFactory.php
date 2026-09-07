<?php

namespace Database\Factories;

use App\Domain\Outputs\DocumentationOutput;
use App\Domain\Outputs\DocumentationOutputType;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentationOutput>
 *
 * Writer fields are not mass-assignable on DocumentationOutput;
 * Factory::create uses Model::unguarded so they can still be set here.
 */
class DocumentationOutputFactory extends Factory
{
    protected $model = DocumentationOutput::class;

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
            'review_cycle_id' => fn (array $attributes): string => ReviewCycle::factory()
                ->forPupil(Pupil::query()->findOrFail($attributes['pupil_id']))
                ->create()
                ->id,
            'type' => DocumentationOutputType::ReviewSummary,
            'version' => 1,
            'confirmer_user_id' => User::factory(),
            'disclaimer_text' => DocumentationOutput::DISCLAIMER_TEXT,
            'confirmed_at' => now(),
            'pack_ready_at' => now(),
            'payload' => [
                'kind' => DocumentationOutputType::ReviewSummary->value,
                'determination_ids' => [],
                'evidence_ids' => [],
                'gap_ids' => [],
            ],
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

    public function forCycle(ReviewCycle $cycle): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $cycle->tenant_id,
            'pupil_id' => $cycle->pupil_id,
            'review_cycle_id' => $cycle->id,
        ]);
    }

    public function confirmedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'confirmer_user_id' => $user->id,
        ]);
    }

    public function reviewSummary(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DocumentationOutputType::ReviewSummary,
            'payload' => [
                'kind' => DocumentationOutputType::ReviewSummary->value,
                'determination_ids' => [],
                'evidence_ids' => [],
                'gap_ids' => [],
            ],
        ]);
    }

    public function ehcpPack(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DocumentationOutputType::EhcpPack,
            'payload' => [
                'kind' => DocumentationOutputType::EhcpPack->value,
                'present' => [
                    'need' => [],
                    'provision' => [],
                    'outcome' => [],
                ],
                'absent' => [],
                'determination_ids' => [],
                'evidence_ids' => [],
                'gap_ids' => [],
            ],
        ]);
    }

    public function version(int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => $version,
        ]);
    }
}
