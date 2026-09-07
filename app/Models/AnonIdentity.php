<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\AnonIdentityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client-generated anonymous visitor id, one per browser/site, before we
 * know who someone is. person_id is filled in once resolved.
 */
#[Fillable(['organization_id', 'person_id'])]
class AnonIdentity extends Model
{
    /** @use HasFactory<AnonIdentityFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /**
     * The resolved person for this identity, if known.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The behavioral events recorded against this identity.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
