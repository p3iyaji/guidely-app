<?php

namespace App\Policies;

use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\OutcomeTerm;
use App\Models\User;

class OutcomeTermPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function view(User $user, OutcomeTerm $outcomeTerm): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->onEffectiveVersion($outcomeTerm);
    }

    public function create(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function update(User $user, OutcomeTerm $outcomeTerm): bool
    {
        return $this->view($user, $outcomeTerm);
    }

    public function delete(User $user, OutcomeTerm $outcomeTerm): bool
    {
        return $this->view($user, $outcomeTerm);
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }

    private function onEffectiveVersion(OutcomeTerm $outcomeTerm): bool
    {
        $versionId = app(EffectiveOntologyVersion::class)->id();

        return $versionId !== null
            && $outcomeTerm->ontology_version_id === $versionId;
    }
}
