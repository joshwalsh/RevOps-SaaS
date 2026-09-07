<?php

use App\Models\AnonIdentity;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TenantPeople;
use App\Models\Transaction;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Organization $organization;

    public TenantPeople $tenantPerson;

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    /**
     * Resolve the tenant's link to this person (never a raw global Person
     * lookup, so one tenant can't probe another tenant's person records)
     * and confirm the current user may view it.
     */
    public function mount(Organization $organization, int $tenantPersonId): void
    {
        $this->tenantPerson = $organization->tenantPeople()->with('person')->findOrFail($tenantPersonId);

        Gate::authorize('view', $this->tenantPerson);

        $this->organization = $organization;
        $this->firstName = $this->tenantPerson->first_name ?? '';
        $this->lastName = $this->tenantPerson->last_name ?? '';
        $this->email = $this->tenantPerson->email ?? '';
    }

    /**
     * Save edits to the captured contact info.
     */
    public function updateContact(): void
    {
        Gate::authorize('update', $this->tenantPerson);

        $validated = $this->validate([
            'firstName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $this->tenantPerson->update([
            'first_name' => $validated['firstName'] ?: null,
            'last_name' => $validated['lastName'] ?: null,
            'email' => $validated['email'] ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $personId = $this->tenantPerson->person_id;

        return [
            'anonIdentitiesCount' => AnonIdentity::query()
                ->forOrganization($this->organization)
                ->where('person_id', $personId)
                ->count(),
            'eventsCount' => Event::query()
                ->forOrganization($this->organization)
                ->where('person_id', $personId)
                ->count(),
            'recentEvents' => Event::query()
                ->forOrganization($this->organization)
                ->where('person_id', $personId)
                ->latest()
                ->limit(20)
                ->get(),
            'transactions' => Transaction::query()
                ->forOrganization($this->organization)
                ->where('person_id', $personId)
                ->latest()
                ->get(),
        ];
    }
}; ?>

<div>
    <div class="max-w-2xl">
        <a href="{{ route('organizations.people.index', $organization) }}" wire:navigate class="text-sm font-medium text-blue-700 hover:text-blue-800">
            &larr; {{ __('Back to People') }}
        </a>

        <h2 class="mt-2 text-lg font-medium text-gray-900">
            {{ $tenantPerson->fullName() ?? $tenantPerson->email ?? __('Unnamed visitor') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('First seen :date · :identities anonymous identities · :events events', [
                'date' => $tenantPerson->first_seen_at->format('M j, Y'),
                'identities' => $anonIdentitiesCount,
                'events' => $eventsCount,
            ]) }}
        </p>
    </div>

    <div class="max-w-2xl mt-10">
        <h3 class="text-lg font-medium text-gray-900">
            {{ __('Contact Info') }}
        </h3>

        @can('update', $tenantPerson)
            <form wire:submit="updateContact" class="mt-6 flex flex-wrap items-end gap-4">
                <div>
                    <x-input-label for="firstName" :value="__('First Name')" />
                    <x-text-input wire:model="firstName" id="firstName" class="block mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="lastName" :value="__('Last Name')" />
                    <x-text-input wire:model="lastName" id="lastName" class="block mt-1 w-full" />
                </div>
                <div class="flex-1 min-w-48">
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input wire:model="email" id="email" type="email" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </form>
        @else
            <div class="mt-6 text-sm text-gray-700">
                <div>{{ __('Name') }}: {{ $tenantPerson->fullName() ?? __('Unknown') }}</div>
                <div>{{ __('Email') }}: {{ $tenantPerson->email ?? __('Unknown') }}</div>
            </div>
        @endcan
    </div>

    <div class="max-w-2xl mt-10">
        <h3 class="text-lg font-medium text-gray-900">
            {{ __('Transactions') }}
        </h3>

        <div class="mt-6 divide-y divide-gray-200 border-t border-gray-200">
            @forelse ($transactions as $transaction)
                <div class="flex items-center justify-between py-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ $transaction->product_name }}</div>
                        <div class="text-sm text-gray-500">{{ $transaction->created_at->format('M j, Y') }}</div>
                    </div>
                    <span class="text-sm font-medium text-gray-900">{{ $transaction->amountLabel() }}</span>
                </div>
            @empty
                <p class="py-4 text-sm text-gray-500">{{ __('No transactions yet.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="max-w-2xl mt-10">
        <h3 class="text-lg font-medium text-gray-900">
            {{ __('Recent Activity') }}
        </h3>

        <div class="mt-6 divide-y divide-gray-200 border-t border-gray-200">
            @forelse ($recentEvents as $event)
                <div class="flex items-center justify-between py-4">
                    <span class="text-sm text-gray-900">{{ $event->event_name }}</span>
                    <span class="text-sm text-gray-500">{{ $event->created_at->format('M j, Y g:ia') }}</span>
                </div>
            @empty
                <p class="py-4 text-sm text-gray-500">{{ __('No activity recorded yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>
