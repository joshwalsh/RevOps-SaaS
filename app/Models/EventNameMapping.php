<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EventNameMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps one raw event_name string (as it appears on events.event_name) to a
 * tenant-defined CanonicalEvent. Deleting the mapping reverts that raw name
 * to "unmapped" without touching any historical Event rows, since
 * classification is resolved by joining this table at query time rather
 * than being stored on each event.
 */
#[Fillable(['organization_id', 'event_name', 'canonical_event_id'])]
class EventNameMapping extends Model
{
    /** @use HasFactory<EventNameMappingFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * The canonical event this raw name rolls up into.
     */
    public function canonicalEvent(): BelongsTo
    {
        return $this->belongsTo(CanonicalEvent::class);
    }
}
