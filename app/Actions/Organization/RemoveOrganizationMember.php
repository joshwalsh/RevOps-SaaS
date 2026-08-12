<?php

namespace App\Actions\Organization;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveOrganizationMember
{
    /**
     * Remove the target member from the organization, used both when an
     * owner/admin removes someone and when a member voluntarily leaves.
     */
    public function __invoke(User $actor, Organization $organization, User $target): void
    {
        Gate::forUser($actor)->authorize('removeMember', [$organization, $target]);

        DB::transaction(function () use ($organization, $target) {
            $organization->users()->detach($target);

            if ($target->current_organization_id === $organization->id) {
                $target->forceFill([
                    'current_organization_id' => $target->organizations()->first()?->id,
                ])->save();
            }
        });
    }
}
