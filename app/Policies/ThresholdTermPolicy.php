<?php

namespace App\Policies;

use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\ThresholdTerm;
use App\Models\User;

class ThresholdTermPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function view(User $user, ThresholdTerm $thresholdTerm): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->onEffectiveVersion($thresholdTerm);
    }

    public function create(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function update(User $user, ThresholdTerm $thresholdTerm): bool
    {
        return $this->view($user, $thresholdTerm);
    }

    public function delete(User $user, ThresholdTerm $thresholdTerm): bool
    {
        return $this->view($user, $thresholdTerm);
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }

    private function onEffectiveVersion(ThresholdTerm $thresholdTerm): bool
    {
        $versionId = app(EffectiveOntologyVersion::class)->id();

        return $versionId !== null
            && $thresholdTerm->ontology_version_id === $versionId;
    }
}
