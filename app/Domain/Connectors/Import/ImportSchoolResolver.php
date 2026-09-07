<?php

namespace App\Domain\Connectors\Import;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\School;
use App\Models\User;

/**
 * Resolve an Import row's School from school_id or school_name within Tenant scope.
 */
class ImportSchoolResolver
{
    /**
     * @param  array<string, string|null>  $row
     * @return array{school?: School, error?: string}
     */
    public function resolve(array $row, User $user): array
    {
        $tenantId = CurrentTenant::id();
        $schoolId = $row['school_id'] ?? null;
        $schoolName = $row['school_name'] ?? null;

        if ($schoolId !== null && $schoolName !== null) {
            return ['error' => 'Provide either school_id or school_name, not both.'];
        }

        if ($schoolId === null && $schoolName === null) {
            return ['error' => 'School is required (school_name or school_id).'];
        }

        if ($schoolId !== null) {
            $school = School::query()
                ->whereKey($schoolId)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();

            if ($school === null) {
                return ['error' => 'The selected School must be an active School in your organisation.'];
            }

            if (! $user->canAccessSchool($school)) {
                return ['error' => 'You do not have access to the selected School.'];
            }

            return ['school' => $school];
        }

        $matches = School::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('name', $schoolName)
            ->get();

        if ($matches->isEmpty()) {
            return ['error' => 'No active School matches this school_name.'];
        }

        if ($matches->count() > 1) {
            return ['error' => 'Multiple Schools match this school_name.'];
        }

        /** @var School $school */
        $school = $matches->first();

        if (! $user->canAccessSchool($school)) {
            return ['error' => 'You do not have access to the selected School.'];
        }

        return ['school' => $school];
    }
}
