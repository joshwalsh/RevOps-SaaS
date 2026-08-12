<?php

namespace App\Actions\Organization;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganizationForUser
{
    /**
     * Create a new organization, make the given user its owner, and switch
     * them into it. Shared by registration (new org on signup) and the
     * "create another organization" flow.
     */
    public function __invoke(User $user, string $name): Organization
    {
        return DB::transaction(function () use ($user, $name) {
            $organization = Organization::create(['name' => $name]);

            $organization->users()->attach($user, ['role' => OrganizationRole::Owner]);

            $user->switchOrganization($organization);

            return $organization;
        });
    }
}
