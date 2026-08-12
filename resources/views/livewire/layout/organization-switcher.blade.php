<?php

use App\Models\Organization;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Switch the current user's active organization.
     */
    public function switchTo(Organization $organization): void
    {
        Gate::authorize('view', $organization);

        auth()->user()->switchOrganization($organization);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'organizations' => auth()->user()->organizations()
                ->orderByDesc('is_super_admin')
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<x-dropdown align="right" width="48">
    <x-slot name="trigger">
        <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
            {{ auth()->user()->currentOrganization?->name ?? __('No Organization') }}

            <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>
    </x-slot>

    <x-slot name="content">
        @foreach ($organizations as $organization)
            <button type="button" wire:click="switchTo({{ $organization->id }})" class="w-full text-start block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                {{ $organization->name }}

                @if ($organization->id === auth()->user()->current_organization_id)
                    <span class="text-indigo-600">&check;</span>
                @endif
            </button>
        @endforeach

        <x-dropdown-link :href="route('organizations.create')" wire:navigate>
            {{ __('Create Organization') }}
        </x-dropdown-link>
    </x-slot>
</x-dropdown>
