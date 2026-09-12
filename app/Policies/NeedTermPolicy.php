<?php

namespace App\Policies;

use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\NeedTerm;
use App\Models\User;

class NeedTermPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function view(User $user, NeedTerm $needTerm): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->onEffectiveVersion($needTerm);
    }

    public function create(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function update(User $user, NeedTerm $needTerm): bool
    {
        return $this->view($user, $needTerm);
    }

    public function delete(User $user, NeedTerm $needTerm): bool
    {
        return $this->view($user, $needTerm);
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }

    private function onEffectiveVersion(NeedTerm $needTerm): bool
    {
        $versionId = app(EffectiveOntologyVersion::class)->id();

        return $versionId !== null
            && $needTerm->ontology_version_id === $versionId;
    }
}
