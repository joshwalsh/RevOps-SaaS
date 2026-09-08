<?php

use App\Models\Organization;
use App\Models\TenantPeople;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public Organization $organization;

    public string $search = '';

    /**
     * Resolve the organization and confirm the current user may view its people.
     */
    public function mount(Organization $organization): void
    {
        Gate::authorize('viewAny', [TenantPeople::class, $organization]);

        $this->organization = $organization;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $people = $this->organization->tenantPeople()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->latest('first_seen_at')
            ->paginate(20);

        return ['people' => $people];
    }
}; ?>

<div>
    <div class="max-w-2xl flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-medium text-gray-900">
                {{ __('People') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('Everyone tracked or identified at :organization.', ['organization' => $organization->name]) }}
            </p>
        </div>

        @can('create', [\App\Models\TenantPeople::class, $organization])
            <a
                href="{{ route('organizations.people.import', $organization) }}"
                wire:navigate
                class="shrink-0 text-sm font-medium text-blue-700 hover:text-blue-800"
            >
                {{ __('Import CSV') }}
            </a>
        @endcan
    </div>

    <div class="mt-6 max-w-2xl">
        <x-text-input wire:model.live="search" type="search" class="block w-full" :placeholder="__('Search by name or email...')" />
    </div>

    <div class="max-w-2xl mt-6 divide-y divide-gray-200 border-t border-gray-200">
        @forelse ($people as $tenantPerson)
            <a
                href="{{ route('organizations.people.show', [$organization, $tenantPerson]) }}"
                wire:navigate
                class="flex items-center justify-between py-4 hover:bg-gray-50"
            >
                <div>
                    <div class="text-sm font-medium text-gray-900">
                        {{ $tenantPerson->fullName() ?? $tenantPerson->email ?? __('Unnamed visitor') }}
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $tenantPerson->email ?? __('No email captured') }}
                    </div>
                </div>

                <span class="text-sm text-gray-500">
                    {{ __('First seen :date', ['date' => $tenantPerson->first_seen_at->format('M j, Y')]) }}
                </span>
            </a>
        @empty
            <p class="py-4 text-sm text-gray-500">{{ __('No one tracked yet.') }}</p>
        @endforelse
    </div>

    <div class="max-w-2xl mt-6">
        {{ $people->links() }}
    </div>
</div>
