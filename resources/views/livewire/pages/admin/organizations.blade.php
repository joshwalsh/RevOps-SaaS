<?php

use App\Models\Organization;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /**
     * Restrict this platform-wide view to super admins.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'organizations' => Organization::withCount('users')
                ->orderByDesc('is_super_admin')
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div>
    <div class="max-w-2xl">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Organizations') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('All organizations on the platform.') }}
        </p>
    </div>

    <div class="mt-6 divide-y divide-gray-200 border-t border-gray-200">
        @foreach ($organizations as $organization)
            <div class="flex items-center justify-between py-4">
                <div>
                    <div class="flex items-center gap-2 text-sm font-medium text-gray-900">
                        {{ $organization->name }}

                        @if ($organization->is_super_admin)
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                {{ __('Platform Admin') }}
                            </span>
                        @endif
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $organization->users_count }} {{ $organization->users_count === 1 ? __('member') : __('members') }}
                    </div>
                </div>

                <a href="{{ route('organizations.members', $organization) }}" wire:navigate class="text-sm font-medium text-blue-700 hover:text-blue-800">
                    {{ __('Manage Members') }}
                </a>
            </div>
        @endforeach
    </div>
</div>
