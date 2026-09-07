<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LogIdentityEventRequest;
use App\Http\Requests\Api\ResolveIdentityRequest;
use App\Http\Requests\Api\TouchIdentityRequest;
use App\Models\AnonIdentity;
use App\Models\Event;
use App\Services\IdentityResolver;
use Illuminate\Http\JsonResponse;

class IdentityController extends Controller
{
    /**
     * Called on every page load. Returns the anonymous identity to keep
     * using for this tenant, creating one if the caller doesn't have one yet.
     */
    public function touch(TouchIdentityRequest $request): JsonResponse
    {
        $tenantId = $request->integer('tenant_id');
        $anonId = $request->string('anon_id')->toString();

        $identity = $anonId !== ''
            ? AnonIdentity::query()->forOrganization($tenantId)->find($anonId)
            : null;

        if ($identity === null) {
            $identity = AnonIdentity::query()->create(['organization_id' => $tenantId]);
        } else {
            $identity->touch();
        }

        return response()->json([
            'anon_id' => $identity->id,
            'person_id' => $identity->person_id,
        ]);
    }

    /**
     * Called once an email becomes known, merging the anonymous identity
     * into a canonical person.
     */
    public function resolve(ResolveIdentityRequest $request, IdentityResolver $resolver): JsonResponse
    {
        $anonId = $request->string('anon_id')->toString();

        $person = $resolver->resolve(
            $request->integer('tenant_id'),
            $anonId,
            $request->string('email')->toString(),
        );

        return response()->json([
            'anon_id' => $anonId,
            'person_id' => $person->id,
        ]);
    }

    /**
     * Records a behavioral event against an anonymous identity, attributing
     * it to a resolved person when one is already known.
     */
    public function event(LogIdentityEventRequest $request): JsonResponse
    {
        $tenantId = $request->integer('tenant_id');

        $identity = AnonIdentity::query()
            ->forOrganization($tenantId)
            ->findOrFail($request->string('anon_id')->toString());

        Event::query()->create([
            'organization_id' => $tenantId,
            'anon_identity_id' => $identity->id,
            'person_id' => $identity->person_id,
            'event_name' => $request->string('event_name')->toString(),
            'properties' => $request->input('properties'),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
