<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view the organization's product catalog.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) !== null;
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        return $user->roleIn($product->organization) !== null;
    }

    /**
     * Determine whether the user can add a product to the organization's catalog.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->isManagerOf($organization);
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->isManagerOf($product->organization);
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->isManagerOf($product->organization);
    }
}
