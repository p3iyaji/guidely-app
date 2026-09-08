<?php

namespace App\Domain\Connectors;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SendStatus;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Upsert a Pupil from a filtered Connector payload by (school_id, mis_key).
 */
class ConnectorPupilUpserter
{
    public function __construct(private AuditWriter $audit) {}

    /**
     * @param  array<string, mixed>  $filtered
     * @return array{pupil?: Pupil, skipped?: bool, error?: string}
     */
    public function upsert(string $schoolId, array $filtered, User $user, Request $request): array
    {
        $misKey = $filtered['mis_key'] ?? null;

        if (is_string($misKey)) {
            $misKey = trim($misKey);
        }

        if (! is_string($misKey) || $misKey === '') {
            return ['skipped' => true];
        }

        $match = Pupil::withTrashed()
            ->where('school_id', $schoolId)
            ->where('mis_key', $misKey)
            ->first();

        if ($match !== null && $match->trashed()) {
            return [
                'skipped' => true,
                'error' => 'A left Pupil already uses this MIS key in this School.',
            ];
        }

        $attributes = $this->attributesFromFiltered($filtered);

        try {
            if ($match !== null) {
                return $this->updateExisting($match, $attributes, $user, $request);
            }

            return $this->createPupil($schoolId, $misKey, $attributes, $user, $request);
        } catch (UniqueConstraintViolationException) {
            return [
                'skipped' => true,
                'error' => 'A Pupil with this MIS key already exists in this School.',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $filtered
     * @return array<string, mixed>
     */
    private function attributesFromFiltered(array $filtered): array
    {
        $attributes = [];

        foreach (ConnectorField::values() as $field) {
            if (! array_key_exists($field, $filtered)) {
                continue;
            }

            $value = $filtered[$field];

            if ($value === null || $value === '') {
                continue;
            }

            if ($field === ConnectorField::MisKey->value) {
                continue;
            }

            $attributes[$field] = $value;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{pupil: Pupil}
     */
    private function updateExisting(Pupil $existing, array $attributes, User $user, Request $request): array
    {
        $existing->fill(Arr::only($attributes, [
            'given_name',
            'family_name',
            'date_of_birth',
            'year_group',
            'send_status',
        ]));

        if (! $existing->isDirty()) {
            return ['pupil' => $existing];
        }

        $existing->save();

        $this->audit->record(
            AuditEventType::PupilUpdated,
            $request,
            $user,
            resourceType: 'pupil',
            resourceId: $existing->id,
            metadata: ['source' => 'connector'],
        );

        return ['pupil' => $existing->refresh()];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{pupil: Pupil}
     */
    private function createPupil(string $schoolId, string $misKey, array $attributes, User $user, Request $request): array
    {
        $payload = array_merge([
            'school_id' => $schoolId,
            'mis_key' => $misKey,
            'given_name' => '',
            'family_name' => '',
            'year_group' => '',
            'send_status' => SendStatus::Neither->value,
        ], $attributes);

        $pupil = Pupil::query()->create($payload);

        $this->audit->record(
            AuditEventType::PupilCreated,
            $request,
            $user,
            resourceType: 'pupil',
            resourceId: $pupil->id,
            metadata: ['source' => 'connector'],
        );

        return ['pupil' => $pupil];
    }
}
