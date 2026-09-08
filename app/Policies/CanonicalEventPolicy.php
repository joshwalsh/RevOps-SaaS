<?php

namespace App\Policies;

use App\Models\CanonicalEvent;
use App\Models\Organization;
use App\Models\User;

class CanonicalEventPolicy
{
    /**
     * Determine whether the user can view the organization's canonical events.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) !== null;
    }

    /**
     * Determine whether the user can view the canonical event.
     */
    public function view(User $user, CanonicalEvent $canonicalEvent): bool
    {
        return $user->roleIn($canonicalEvent->organization) !== null;
    }

    /**
     * Determine whether the user can define a new canonical event.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->isManagerOf($organization);
    }

    /**
     * Determine whether the user can rename the canonical event.
     */
    public function update(User $user, CanonicalEvent $canonicalEvent): bool
    {
        return $user->isManagerOf($canonicalEvent->organization);
    }

    /**
     * Determine whether the user can delete the canonical event.
     */
    public function delete(User $user, CanonicalEvent $canonicalEvent): bool
    {
        return $user->isManagerOf($canonicalEvent->organization);
    }
}
