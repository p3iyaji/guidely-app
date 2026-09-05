<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Future SSO enablement helper: resolve a Tenant User by email and attach external_id.
 *
 * Does not call a live IdP. When no User matches the email, returns null without
 * creating a row — the create path remains intentionally stubbed for Pilot.
 */
class LinkExternalIdByEmail
{
    /**
     * Match by case-insensitive email (same lowercasing as AuthController).
     * Links external_id on the existing User; never creates a duplicate account.
     *
     * @throws InvalidArgumentException when external_id is blank after trim
     * @throws ExternalIdConflictException when external_id belongs to another User,
     *                                     or the matched User already has a different external_id
     */
    public function handle(string $tenantId, string $email, string $externalId): ?User
    {
        $normalisedEmail = Str::lower(trim($email));
        $externalId = trim($externalId);

        if ($externalId === '') {
            throw new InvalidArgumentException('external_id must not be blank.');
        }

        $existingWithExternalId = User::query()
            ->where('tenant_id', $tenantId)
            ->where('external_id', $externalId)
            ->first();

        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('lower(email) = ?', [$normalisedEmail])
            ->first();

        if ($existingWithExternalId !== null) {
            if ($user === null || $existingWithExternalId->isNot($user)) {
                throw new ExternalIdConflictException;
            }

            if ($existingWithExternalId->isDeactivated()) {
                return null;
            }

            return $existingWithExternalId;
        }

        if ($user === null || $user->isDeactivated()) {
            return null;
        }

        if ($user->external_id !== null && $user->external_id !== $externalId) {
            throw new ExternalIdConflictException;
        }

        $user->forceFill([
            'external_id' => $externalId,
        ])->save();

        return $user->refresh();
    }
}
