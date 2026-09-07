<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\TenantPeople;
use App\Models\User;

class TenantPeoplePolicy
{
    /**
     * Determine whether the user can view the organization's people.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) !== null;
    }

    /**
     * Determine whether the user can view this tenant's link to a person.
     */
    public function view(User $user, TenantPeople $tenantPerson): bool
    {
        return $user->roleIn($tenantPerson->organization) !== null;
    }

    /**
     * Determine whether the user can edit the captured contact info.
     */
    public function update(User $user, TenantPeople $tenantPerson): bool
    {
        return $user->isManagerOf($tenantPerson->organization);
    }
}
