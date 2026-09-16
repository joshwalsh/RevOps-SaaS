<?php

namespace App\Models;

use App\Enums\TransactionStatus;
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
 *
 * The product link is optional: product_name is always recorded from the
 * signup itself, even when it can't be matched to a Product record (or the
 * matched product is later deleted).
 *
 * amount_cents is the single amount the transaction is recorded at (what
 * amountLabel()/isFree() and product-price snapshotting use) — it doubles
 * as the "net amount" of the accounting breakdown below, since a separate
 * net_amount field would just duplicate it.
 *
 * subtotal_cents, tax_cents, total_cents and fees_cents are an optional
 * accounting breakdown (e.g. from an imported payment processor export),
 * ordered the way they're computed: total is subtotal plus tax, and
 * amount_cents (net) is total minus fees. When amount_cents isn't supplied
 * directly, it's filled in from this chain.
 *
 * discount_cents is informational only: by the time a subtotal is recorded
 * it has already been applied, so it doesn't feed into the chain above —
 * it's stored purely for reference, always as a positive number regardless
 * of how the source system signs it.
 *
 * All breakdown amounts (including amount_cents itself) are always stored
 * as positive numbers — a source system representing a refund as a negative
 * amount is instead expressed via status being Refund, not by the sign of
 * any field.
 *
 * external_id is the source system's identifier for this transaction, when
 * one is available. It's unique per organization and is what re-running an
 * import matches on (via updateOrCreate) to avoid creating duplicates.
 * Rows without one are always inserted fresh, since there's nothing to
 * match against.
 */
#[Fillable([
    'organization_id', 'person_id', 'product_id', 'product_name', 'external_id', 'amount_cents', 'currency', 'status',
    'subtotal_cents', 'tax_cents', 'total_cents', 'fees_cents', 'discount_cents',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
        ];
    }

    /**
     * Fill in amount_cents from the accounting breakdown whenever it isn't
     * supplied directly.
     */
    protected static function booted(): void
    {
        static::saving(function (self $transaction) {
            if ($transaction->amount_cents === null) {
                $transaction->amount_cents = $transaction->computeAmountFromBreakdown();
            }
        });
    }

    /**
     * The person who signed up.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The catalog product signed up for, if it could be matched to one.
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

    /**
     * A human-readable amount, e.g. "Free" or "$29.00 USD".
     */
    public function amountLabel(): string
    {
        return $this->isFree()
            ? __('Free')
            : '$'.number_format($this->amount_cents / 100, 2).' '.$this->currency;
    }

    /**
     * This organization's captured contact info for the transaction's
     * person. Only meaningful when person.tenantPeople has been eager
     * loaded scoped to this transaction's organization_id.
     */
    public function tenantContact(): ?TenantPeople
    {
        return $this->person?->tenantPeople->first();
    }

    /**
     * Derive the (net) amount from the accounting breakdown: the total (or,
     * if none was given, subtotal plus tax) minus fees. discount_cents is
     * deliberately not part of this — it's already reflected in subtotal
     * and is stored only for reference. Yields 0 if nothing was given
     * either, so a transaction with no monetary fields at all is free
     * rather than failing to save. Always non-negative, since amount_cents
     * is an unsigned column — an unusual fees-exceeds-total case is
     * absolute-valued rather than left negative.
     */
    public function computeAmountFromBreakdown(): int
    {
        $total = $this->total_cents ?? (($this->subtotal_cents ?? 0) + ($this->tax_cents ?? 0));

        return abs($total - ($this->fees_cents ?? 0));
    }
}
