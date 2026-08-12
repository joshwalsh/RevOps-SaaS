<?php

namespace App\Console\Commands;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('organizations:promote-super-admin {email} {--role=owner : The role to grant within the super-admin organization (owner, admin, member)}')]
#[Description('Grant a user super-admin powers by attaching them to the platform super-admin organization')]
class PromoteSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("No user found with email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        $role = OrganizationRole::tryFrom($this->option('role'));

        if ($role === null) {
            $this->error("Invalid role [{$this->option('role')}]. Expected one of: owner, admin, member.");

            return self::FAILURE;
        }

        $organization = Organization::superAdmin()->first() ?? $this->createSuperAdminOrganization();

        if ($user->organizations()->whereKey($organization->id)->exists()) {
            $this->info("{$user->email} is already a super admin.");

            return self::SUCCESS;
        }

        $organization->users()->attach($user, ['role' => $role]);

        if ($user->current_organization_id === null) {
            $user->switchOrganization($organization);
        }

        $this->info("{$user->email} is now a super admin ({$role->value}).");

        return self::SUCCESS;
    }

    private function createSuperAdminOrganization(): Organization
    {
        $organization = Organization::create([
            'name' => 'Platform Administration',
            'slug' => 'platform-administration',
        ]);

        $organization->forceFill(['is_super_admin' => true])->save();

        return $organization;
    }
}
