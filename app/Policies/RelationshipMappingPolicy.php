<?php

namespace App\Policies;

use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\RelationshipMapping;
use App\Models\User;

class RelationshipMappingPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function view(User $user, RelationshipMapping $relationshipMapping): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->onEffectiveVersion($relationshipMapping);
    }

    public function create(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function update(User $user, RelationshipMapping $relationshipMapping): bool
    {
        return $this->view($user, $relationshipMapping);
    }

    public function delete(User $user, RelationshipMapping $relationshipMapping): bool
    {
        return $this->view($user, $relationshipMapping);
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }

    private function onEffectiveVersion(RelationshipMapping $relationshipMapping): bool
    {
        $versionId = app(EffectiveOntologyVersion::class)->id();

        return $versionId !== null
            && $relationshipMapping->ontology_version_id === $versionId;
    }
}
