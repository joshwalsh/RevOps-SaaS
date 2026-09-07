<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Canonical cross-tenant visitor identity, keyed by a hash of their email.
 * Distinct from User, which is for authenticated app-login accounts.
 */
#[Fillable(['email_hash'])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, HasUlids;

    /**
     * The per-tenant links for this person.
     */
    public function tenantPeople(): HasMany
    {
        return $this->hasMany(TenantPeople::class);
    }

    /**
     * The anonymous identities that have been resolved to this person.
     */
    public function anonIdentities(): HasMany
    {
        return $this->hasMany(AnonIdentity::class);
    }

    /**
     * The events attributed to this resolved person.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * The product signup transactions attributed to this person.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
