<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant's catalog offering that people can sign up for, free or paid.
 */
#[Fillable(['organization_id', 'name', 'price_cents', 'currency'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * The signup transactions recorded against this product.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Determine whether this product currently has no cost to sign up for.
     */
    public function isFree(): bool
    {
        return $this->price_cents === 0;
    }

    /**
     * A human-readable price, e.g. "Free" or "$29.00 USD".
     */
    public function priceLabel(): string
    {
        return $this->isFree()
            ? __('Free')
            : '$'.number_format($this->price_cents / 100, 2).' '.$this->currency;
    }
}
