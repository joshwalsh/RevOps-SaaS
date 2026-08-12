<?php

use App\Actions\Organization\RemoveOrganizationMember;
use App\Enums\OrganizationRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Organization $organization;

    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    /**
     * Resolve the organization and confirm the current user may view it.
     */
    public function mount(Organization $organization): void
    {
        Gate::authorize('view', $organization);

        $this->organization = $organization;
    }

    /**
     * Invite a new member to the organization by email.
     */
    public function invite(): void
    {
        $role = OrganizationRole::from($this->inviteRole);

        Gate::authorize('addMember', [$this->organization, $role]);

        $validated = $this->validate([
            'inviteEmail' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('organization_invitations', 'email')
                    ->where('organization_id', $this->organization->id),
            ],
        ]);

        $invitation = $this->organization->invitations()->create([
            'email' => $validated['inviteEmail'],
            'role' => $role,
            'invited_by' => auth()->id(),
        ]);

        Mail::to($invitation->email)->send(new OrganizationInvitationMail($invitation));

        $this->reset('inviteEmail', 'inviteRole');
    }

    /**
     * Remove a member from the organization, or leave it voluntarily.
     */
    public function removeMember(int $userId, RemoveOrganizationMember $removeMember): void
    {
        $removeMember(auth()->user(), $this->organization, User::findOrFail($userId));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'members' => $this->organization->users()->get(),
            'pendingInvitations' => $this->organization->invitations()->latest()->get(),
            'roles' => OrganizationRole::cases(),
        ];
    }
}; ?>

<div>
    <div class="max-w-2xl">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Members') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('People who have access to :organization.', ['organization' => $organization->name]) }}
        </p>

        <div class="mt-6 divide-y divide-gray-200 border-t border-gray-200">
            @foreach ($members as $member)
                <div class="flex items-center justify-between py-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ $member->name }}</div>
                        <div class="text-sm text-gray-500">{{ $member->email }}</div>
                    </div>

                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-500">{{ ucfirst($member->pivot->role->value) }}</span>

                        @can('removeMember', [$organization, $member])
                            <x-danger-button
                                wire:click="removeMember({{ $member->id }})"
                                wire:confirm="{{ __('Are you sure?') }}"
                            >
                                {{ $member->is(auth()->user()) ? __('Leave') : __('Remove') }}
                            </x-danger-button>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($pendingInvitations->isNotEmpty())
        <div class="max-w-2xl mt-10">
            <h3 class="text-lg font-medium text-gray-900">
                {{ __('Pending Invitations') }}
            </h3>

            <div class="mt-6 divide-y divide-gray-200 border-t border-gray-200">
                @foreach ($pendingInvitations as $invitation)
                    <div class="flex items-center justify-between py-4">
                        <div class="text-sm text-gray-900">{{ $invitation->email }}</div>
                        <span class="text-sm text-gray-500">{{ ucfirst($invitation->role->value) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="max-w-2xl mt-10">
        <h3 class="text-lg font-medium text-gray-900">
            {{ __('Invite Member') }}
        </h3>

        <form wire:submit="invite" class="mt-6 flex items-end gap-4">
            <div class="flex-1">
                <x-input-label for="inviteEmail" :value="__('Email')" />
                <x-text-input wire:model="inviteEmail" id="inviteEmail" class="block mt-1 w-full" type="email" name="inviteEmail" />
                <x-input-error :messages="$errors->get('inviteEmail')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="inviteRole" :value="__('Role')" />
                <select wire:model="inviteRole" id="inviteRole" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}">{{ ucfirst($role->value) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('inviteRole')" class="mt-2" />
            </div>

            <x-primary-button>
                {{ __('Send Invite') }}
            </x-primary-button>
        </form>
    </div>
</div>
