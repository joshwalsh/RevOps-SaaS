<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\CanonicalEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant-defined event, e.g. "Page View", that one or more raw
 * event_name strings (page_view, page_render, ...) can be mapped to via
 * EventNameMapping.
 */
#[Fillable(['organization_id', 'name'])]
class CanonicalEvent extends Model
{
    /** @use HasFactory<CanonicalEventFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * The raw event_name mappings that roll up into this canonical event.
     */
    public function mappings(): HasMany
    {
        return $this->hasMany(EventNameMapping::class);
    }
}
