<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OrganizationRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The organizations this user belongs to.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(OrganizationUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The organization the user is currently acting within.
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    /**
     * Determine if the user is a super admin by virtue of belonging to the
     * platform's super-admin organization, regardless of which organization
     * is currently active.
     */
    public function isSuperAdmin(): bool
    {
        return $this->organizations()->where('is_super_admin', true)->exists();
    }

    /**
     * Determine the role this user holds within the given organization, if any.
     */
    public function roleIn(Organization $organization): ?OrganizationRole
    {
        $membership = $this->organizations->firstWhere('id', $organization->id);

        return $membership?->pivot->role;
    }

    /**
     * Determine if the user holds the given role within the given organization.
     */
    public function hasRole(Organization $organization, OrganizationRole $role): bool
    {
        return $this->roleIn($organization) === $role;
    }

    /**
     * Switch the user's currently active organization.
     */
    public function switchOrganization(Organization $organization): void
    {
        if (! $this->organizations()->whereKey($organization->id)->exists()) {
            throw new \InvalidArgumentException('User is not a member of this organization.');
        }

        $this->forceFill(['current_organization_id' => $organization->id])->save();
    }
}
