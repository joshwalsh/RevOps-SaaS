<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            if (! $user->isSuperAdmin()) {
                return null;
            }

            // Never bypass the policy's hard block on deleting the super-admin
            // organization itself, even for a super admin.
            $target = $arguments[0] ?? null;

            if ($ability === 'delete' && $target instanceof Organization && $target->is_super_admin) {
                return null;
            }

            return true;
        });
    }
}
