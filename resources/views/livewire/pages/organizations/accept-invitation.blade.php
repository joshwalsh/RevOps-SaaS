<?php

use App\Livewire\Actions\Logout;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public OrganizationInvitation $invitation;

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $loginPassword = '';

    /**
     * Resolve the invitation, silently consuming it up front if the invited
     * email already belongs to a member of the organization (stale invite).
     */
    public function mount(OrganizationInvitation $invitation): void
    {
        $this->invitation = $invitation;

        $existingUser = $this->existingUser;

        if ($existingUser && $existingUser->organizations()->whereKey($invitation->organization_id)->exists()) {
            $invitation->delete();

            session()->flash('status', __('You are already a member of this organization.'));

            $this->redirect(Auth::check() ? route('dashboard', absolute: false) : route('login'), navigate: true);
        }
    }

    /**
     * The user record matching the invited email, if one already exists.
     */
    public function getExistingUserProperty(): ?User
    {
        return User::where('email', $this->invitation->email)->first();
    }

    /**
     * Whether the currently authenticated user is a different account than
     * the one this invitation was sent to.
     */
    public function getMismatchedAccountProperty(): bool
    {
        return Auth::check() && Auth::user()->email !== $this->invitation->email;
    }

    /**
     * Register a brand-new account for the invited email and join the organization.
     */
    public function register(): void
    {
        abort_if($this->existingUser !== null, 404);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $this->invitation->email,
                'password' => Hash::make($validated['password']),
            ]);

            // Clicking a signed link emailed to this address proves inbox control.
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->acceptFor($user);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        $this->invitation->delete();

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Log in as the invited (existing, unauthenticated) user and join the organization.
     */
    public function login(): void
    {
        $this->validate(['loginPassword' => ['required', 'string']]);

        if (! Auth::attempt(['email' => $this->invitation->email, 'password' => $this->loginPassword])) {
            $this->addError('loginPassword', __('These credentials do not match our records.'));

            return;
        }

        $this->acceptFor(Auth::user());

        $this->invitation->delete();

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Accept the invitation as the already-authenticated, matching user.
     */
    public function accept(): void
    {
        abort_unless(Auth::check() && Auth::user()->email === $this->invitation->email, 403);

        $this->acceptFor(Auth::user());

        $this->invitation->delete();

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    private function acceptFor(User $user): void
    {
        $this->invitation->organization->users()->attach($user, ['role' => $this->invitation->role]);

        $user->switchOrganization($this->invitation->organization);
    }

    /**
     * Log the mismatched account out so they can retry as the invited user.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('login'), navigate: true);
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-gray-600">
        {{ __("You've been invited to join :organization.", ['organization' => $invitation->organization->name]) }}
    </div>

    @if ($this->mismatchedAccount)
        <div class="mb-4 text-sm text-red-600">
            {{ __('This invitation was sent to :email, but you are signed in as :current.', ['email' => $invitation->email, 'current' => auth()->user()->email]) }}
        </div>

        <button wire:click="logout" class="underline text-sm text-gray-600 hover:text-gray-900">
            {{ __('Log out and try again') }}
        </button>
    @elseif (auth()->check())
        <x-primary-button wire:click="accept">
            {{ __('Accept Invitation') }}
        </x-primary-button>
    @elseif ($this->existingUser)
        <form wire:submit="login">
            <div>
                <x-input-label for="loginPassword" :value="__('Password')" />
                <x-text-input wire:model="loginPassword" id="loginPassword" class="block mt-1 w-full" type="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('loginPassword')" class="mt-2" />
            </div>

            <div class="flex justify-end mt-4">
                <x-primary-button>
                    {{ __('Log In &amp; Accept') }}
                </x-primary-button>
            </div>
        </form>
    @else
        <form wire:submit="register">
            <div>
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex justify-end mt-4">
                <x-primary-button>
                    {{ __('Create Account &amp; Accept') }}
                </x-primary-button>
            </div>
        </form>
    @endif
</div>
