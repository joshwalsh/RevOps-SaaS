<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine whether the user can view the organization's transactions.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) !== null;
    }

    /**
     * Determine whether the user can view the transaction.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        return $user->roleIn($transaction->organization) !== null;
    }

    /**
     * Determine whether the user can manually record a signup transaction.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->isManagerOf($organization);
    }
}
