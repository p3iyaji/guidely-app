<?php

namespace App\Policies;

use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\SettingTerm;
use App\Models\User;

class SettingTermPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function view(User $user, SettingTerm $settingTerm): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->onEffectiveVersion($settingTerm);
    }

    public function create(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function update(User $user, SettingTerm $settingTerm): bool
    {
        return $this->view($user, $settingTerm);
    }

    public function delete(User $user, SettingTerm $settingTerm): bool
    {
        return $this->view($user, $settingTerm);
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }

    private function onEffectiveVersion(SettingTerm $settingTerm): bool
    {
        $versionId = app(EffectiveOntologyVersion::class)->id();

        return $versionId !== null
            && $settingTerm->ontology_version_id === $versionId;
    }
}
