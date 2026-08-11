<?php

use App\Actions\Organization\CreateOrganizationForUser;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';

    /**
     * Create a new organization and switch the current user into it.
     */
    public function create(CreateOrganizationForUser $createOrganization): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $createOrganization(auth()->user(), $validated['name']);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Give your organization a name to get started.') }}
    </div>

    <form wire:submit="create">
        <div>
            <x-input-label for="name" :value="__('Organization Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                {{ __('Create Organization') }}
            </x-primary-button>
        </div>
    </form>
</div>
