<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\TenantPeopleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
     * The person's full name, combined from first_name/last_name on read.
     * Setting it (e.g. `$tenantPerson->full_name = 'Ada Lovelace'`) splits a
     * single name string on its first space into first_name/last_name,
     * which is convenient for CSV imports that only have one name column.
     * A name with no space becomes the first name alone.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

                return $name === '' ? null : $name;
            },
            set: function (?string $value) {
                $value = trim((string) $value);

                if ($value === '') {
                    return ['first_name' => null, 'last_name' => null];
                }

                [$firstName, $lastName] = array_pad(explode(' ', $value, 2), 2, null);

                return ['first_name' => $firstName, 'last_name' => $lastName];
            },
        );
    }
}
