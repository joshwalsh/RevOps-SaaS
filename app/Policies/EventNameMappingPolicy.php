<?php

namespace App\Policies;

use App\Models\EventNameMapping;
use App\Models\Organization;
use App\Models\User;

class EventNameMappingPolicy
{
    /**
     * Determine whether the user can map a raw event name to a canonical event.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->isManagerOf($organization);
    }

    /**
     * Determine whether the user can change or remove this mapping.
     */
    public function update(User $user, EventNameMapping $eventNameMapping): bool
    {
        return $user->isManagerOf($eventNameMapping->organization);
    }

    /**
     * Determine whether the user can remove this mapping.
     */
    public function delete(User $user, EventNameMapping $eventNameMapping): bool
    {
        return $user->isManagerOf($eventNameMapping->organization);
    }
}
