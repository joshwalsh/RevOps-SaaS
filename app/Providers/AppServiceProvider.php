<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        // Public, unauthenticated tracking endpoints called on every pageview
        // across every tenant site; keyed by IP + tenant so one tenant's
        // traffic can't exhaust another's allowance.
        RateLimiter::for('identity-api', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip().'|'.$request->input('tenant_id'));
        });

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
