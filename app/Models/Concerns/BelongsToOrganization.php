<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * These models are written by public, unauthenticated tenant API endpoints
 * keyed on a tenant id supplied by the caller, so there is no authenticated
 * session to scope against automatically like the app-login side of the
 * codebase. Every query against them must be scoped explicitly via
 * forOrganization() to avoid cross-tenant leakage.
 */
trait BelongsToOrganization
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, Organization|int|string $organization): Builder
    {
        return $query->where('organization_id', $organization instanceof Organization ? $organization->id : $organization);
    }
}
