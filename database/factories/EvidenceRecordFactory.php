<?php

namespace Database\Factories;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceSource;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Pupils\Pupil;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvidenceRecord>
 */
class EvidenceRecordFactory extends Factory
{
    protected $model = EvidenceRecord::class;

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
            'author_id' => User::factory(),
            'type' => EvidenceType::Observation,
            'lifecycle' => EvidenceLifecycle::Submitted,
            'source' => null,
            'external_id' => null,
            'occurred_at' => now()->utc(),
            'setting_term_id' => SettingTerm::factory(),
            'provision_term_id' => null,
            'related_intervention_id' => null,
            'body' => fake()->sentence(),
        ];
    }

    public function fromImport(?string $externalId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => EvidenceSource::Import,
            'external_id' => $externalId ?? fake()->uuid(),
        ]);
    }

    public function forPupil(Pupil $pupil): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $pupil->tenant_id,
            'pupil_id' => $pupil->id,
        ]);
    }

    public function authoredBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'author_id' => $user->id,
        ]);
    }

    public function withSetting(SettingTerm $term): static
    {
        return $this->state(fn (array $attributes) => [
            'setting_term_id' => $term->id,
        ]);
    }

    public function intervention(?ProvisionTerm $term = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EvidenceType::Intervention,
            'setting_term_id' => null,
            'provision_term_id' => $term?->id ?? ProvisionTerm::factory(),
        ]);
    }

    public function withProvision(ProvisionTerm $term): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EvidenceType::Intervention,
            'setting_term_id' => null,
            'provision_term_id' => $term->id,
        ]);
    }

    public function response(?EvidenceRecord $intervention = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EvidenceType::Response,
            'setting_term_id' => null,
            'provision_term_id' => null,
            'related_intervention_id' => $intervention?->id,
        ]);
    }

    public function reviewNote(): static
    {
        return $this->state(function (array $attributes) {
            $body = $attributes['body'] ?? null;
            $occurredAt = $attributes['occurred_at'] ?? null;

            return [
                'type' => EvidenceType::ReviewNote,
                'lifecycle' => EvidenceLifecycle::Submitted,
                'source' => EvidenceSource::Capture,
                'setting_term_id' => null,
                'provision_term_id' => null,
                'related_intervention_id' => null,
                'body' => is_string($body) && trim($body) !== '' ? $body : fake()->sentence(),
                'occurred_at' => $occurredAt ?? now()->utc(),
            ];
        });
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle' => EvidenceLifecycle::Draft,
        ]);
    }
}
