<?php

namespace App\Services;

use App\Models\AnonIdentity;
use App\Models\Event;
use App\Models\Person;
use App\Models\TenantPeople;
use Illuminate\Support\Facades\DB;

class IdentityResolver
{
    /**
     * Resolve an anonymous identity to a canonical person by email, merging
     * it into the tenant's records. Idempotent: re-running with the same
     * inputs reuses the existing person and tenant link rather than
     * duplicating them.
     */
    public function resolve(string $organizationId, string $anonIdentityId, string $email): Person
    {
        $emailHash = hash('sha256', mb_strtolower(trim($email)));

        return DB::transaction(function () use ($organizationId, $anonIdentityId, $emailHash) {
            $person = Person::query()->firstOrCreate(['email_hash' => $emailHash]);

            TenantPeople::query()->firstOrCreate(
                ['organization_id' => $organizationId, 'person_id' => $person->id],
                ['first_seen_at' => now()],
            );

            Event::query()
                ->forOrganization($organizationId)
                ->where('anon_identity_id', $anonIdentityId)
                ->whereNull('person_id')
                ->update(['person_id' => $person->id]);

            AnonIdentity::query()
                ->forOrganization($organizationId)
                ->whereKey($anonIdentityId)
                ->update(['person_id' => $person->id]);

            return $person;
        });
    }
}
