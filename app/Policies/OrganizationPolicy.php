<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Determine whether the user can view the organization.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) !== null;
    }

    /**
     * Determine whether the user can update the organization's settings.
     */
    public function update(User $user, Organization $organization): bool
    {
        return $this->hasManagerRole($user, $organization);
    }

    /**
     * Determine whether the user can add a member with the given role.
     * Only an Owner may grant the Owner role, preventing Admin self-escalation.
     */
    public function addMember(User $user, Organization $organization, OrganizationRole $role = OrganizationRole::Member): bool
    {
        if ($role === OrganizationRole::Owner) {
            return $user->hasRole($organization, OrganizationRole::Owner);
        }

        return $this->hasManagerRole($user, $organization);
    }

    /**
     * Determine whether the user can change an existing member's role.
     */
    public function updateMemberRole(User $user, Organization $organization): bool
    {
        return $user->hasRole($organization, OrganizationRole::Owner);
    }

    /**
     * Determine whether the user can remove the target member from the organization.
     */
    public function removeMember(User $user, Organization $organization, User $target): bool
    {
        $targetRole = $organization->roleFor($target);

        if ($targetRole === null) {
            return false;
        }

        if ($targetRole === OrganizationRole::Owner && $this->isLastOwner($organization)) {
            return false;
        }

        if ($user->is($target)) {
            return true;
        }

        if ($targetRole === OrganizationRole::Owner || $targetRole === OrganizationRole::Admin) {
            return $user->hasRole($organization, OrganizationRole::Owner);
        }

        return $this->hasManagerRole($user, $organization);
    }

    /**
     * Determine whether the user can delete the organization.
     * The super-admin organization can never be deleted, even by its owner.
     */
    public function delete(User $user, Organization $organization): bool
    {
        if ($organization->is_super_admin) {
            return false;
        }

        return $user->hasRole($organization, OrganizationRole::Owner);
    }

    protected function hasManagerRole(User $user, Organization $organization): bool
    {
        return $user->hasRole($organization, OrganizationRole::Owner)
            || $user->hasRole($organization, OrganizationRole::Admin);
    }

    protected function isLastOwner(Organization $organization): bool
    {
        return $organization->users()->wherePivot('role', OrganizationRole::Owner)->count() <= 1;
    }
}
