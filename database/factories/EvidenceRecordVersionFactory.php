<?php

namespace Database\Factories;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceRecordVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvidenceRecordVersion>
 */
class EvidenceRecordVersionFactory extends Factory
{
    protected $model = EvidenceRecordVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evidence_record_id' => EvidenceRecord::factory(),
            'tenant_id' => fn (array $attributes): string => EvidenceRecord::query()
                ->findOrFail($attributes['evidence_record_id'])
                ->tenant_id,
            'version' => 1,
            'snapshot' => [
                'type' => 'observation',
                'author_id' => null,
                'occurred_at' => now()->utc()->toIso8601String(),
                'setting_term_id' => null,
                'provision_term_id' => null,
                'related_intervention_id' => null,
                'body' => fake()->sentence(),
            ],
            'superseded_at' => now()->utc(),
            'superseded_by' => User::factory(),
        ];
    }

    public function forRecord(EvidenceRecord $record): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $record->tenant_id,
            'evidence_record_id' => $record->id,
            'snapshot' => [
                'type' => $record->type->value,
                'author_id' => $record->author_id,
                'occurred_at' => $record->occurred_at?->utc()->toIso8601String(),
                'setting_term_id' => $record->setting_term_id,
                'provision_term_id' => $record->provision_term_id,
                'related_intervention_id' => $record->related_intervention_id,
                'body' => $record->body,
            ],
        ]);
    }

    public function supersededBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'superseded_by' => $user->id,
        ]);
    }
}
