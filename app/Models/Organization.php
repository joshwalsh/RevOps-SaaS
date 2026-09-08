<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * Auto-generate a unique slug from the name when one isn't given explicitly.
     */
    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            if (blank($organization->slug)) {
                $organization->slug = static::uniqueSlugFor($organization->name);
            }
        });
    }

    protected static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * The users that belong to the organization.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The pending invitations for the organization.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * This tenant's links to the canonical people it has identified.
     */
    public function tenantPeople(): HasMany
    {
        return $this->hasMany(TenantPeople::class);
    }

    /**
     * The tenant's catalog of products.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The tenant's recorded signup transactions.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The tenant's recorded behavioral events.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * The tenant's user-defined canonical events.
     */
    public function canonicalEvents(): HasMany
    {
        return $this->hasMany(CanonicalEvent::class);
    }

    /**
     * The tenant's raw event_name -> canonical event mappings.
     */
    public function eventNameMappings(): HasMany
    {
        return $this->hasMany(EventNameMapping::class);
    }

    /**
     * Determine the role a user holds within this organization, if any.
     */
    public function roleFor(User $user): ?OrganizationRole
    {
        $membership = $this->users->firstWhere('id', $user->id);

        return $membership?->pivot->role;
    }

    /**
     * Scope a query to the platform's super-admin organization(s).
     */
    protected function scopeSuperAdmin(Builder $query): void
    {
        $query->where('is_super_admin', true);
    }
}
