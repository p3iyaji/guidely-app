<?php

namespace App\Policies;

use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\ProvisionTerm;
use App\Models\User;

class ProvisionTermPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function view(User $user, ProvisionTerm $provisionTerm): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->onEffectiveVersion($provisionTerm);
    }

    public function create(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function update(User $user, ProvisionTerm $provisionTerm): bool
    {
        return $this->view($user, $provisionTerm);
    }

    public function delete(User $user, ProvisionTerm $provisionTerm): bool
    {
        return $this->view($user, $provisionTerm);
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }

    private function onEffectiveVersion(ProvisionTerm $provisionTerm): bool
    {
        $versionId = app(EffectiveOntologyVersion::class)->id();

        return $versionId !== null
            && $provisionTerm->ontology_version_id === $versionId;
    }
}
