<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A behavioral event tied to an anonymous identity and, once resolved, the
 * canonical person behind it.
 */
#[Fillable(['organization_id', 'anon_identity_id', 'person_id', 'event_name', 'properties'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * Events only ever record when they happened, not when they were touched.
     */
    const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * The anonymous identity this event was recorded against.
     */
    public function anonIdentity(): BelongsTo
    {
        return $this->belongsTo(AnonIdentity::class);
    }

    /**
     * The resolved person this event is attributed to, if known.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
