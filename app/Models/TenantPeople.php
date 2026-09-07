<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\TenantPeopleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a canonical Person to a tenant, since the same person may relate to
 * a tenant independently of any other tenant. Contact details (name, email)
 * are captured here rather than on Person, since Person is shared across
 * tenants and one tenant's captured contact info shouldn't leak into
 * another tenant's view of the same canonical person.
 */
#[Fillable(['organization_id', 'person_id', 'first_seen_at', 'first_name', 'last_name', 'email'])]
class TenantPeople extends Model
{
    /** @use HasFactory<TenantPeopleFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
        ];
    }

    /**
     * The canonical person this link belongs to.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The person's full name from the contact info captured by this tenant,
     * or null if none has been captured yet.
     */
    public function fullName(): ?string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name === '' ? null : $name;
    }
}
