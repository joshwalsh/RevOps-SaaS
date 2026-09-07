<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record of a person signing up for a product, free or paid. The cost is
 * snapshotted at the time of signup rather than read from the product's
 * current price, so historical transactions stay accurate if pricing changes.
 */
#[Fillable(['organization_id', 'person_id', 'product_id', 'amount_cents', 'currency'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * The person who signed up.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The product signed up for.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Determine whether this signup was free.
     */
    public function isFree(): bool
    {
        return $this->amount_cents === 0;
    }
}
