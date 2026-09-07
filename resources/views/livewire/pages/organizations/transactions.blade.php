<?php

use App\Models\Organization;
use App\Models\Transaction;
use App\Services\IdentityResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public Organization $organization;

    public string $search = '';

    public string $personMode = 'existing';

    public string $personId = '';

    public string $newFirstName = '';

    public string $newLastName = '';

    public string $newEmail = '';

    public string $productMode = 'existing';

    public string $productId = '';

    public string $productName = '';

    public string $amount = '0.00';

    public string $currency = 'USD';

    /**
     * Resolve the organization and confirm the current user may view its transactions.
     */
    public function mount(Organization $organization): void
    {
        Gate::authorize('viewAny', [Transaction::class, $organization]);

        $this->organization = $organization;
    }

    /**
     * Manually record a signup transaction.
     */
    public function record(IdentityResolver $resolver): void
    {
        Gate::authorize('create', [Transaction::class, $this->organization]);

        $rules = [
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ];

        if ($this->personMode === 'existing') {
            $rules['personId'] = [
                'required',
                'string',
                Rule::exists('tenant_people', 'person_id')->where('organization_id', $this->organization->id),
            ];
        } else {
            $rules['newFirstName'] = ['nullable', 'string', 'max:255'];
            $rules['newLastName'] = ['nullable', 'string', 'max:255'];
            $rules['newEmail'] = ['nullable', 'email', 'max:255'];
        }

        if ($this->productMode === 'existing') {
            $rules['productId'] = [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('organization_id', $this->organization->id),
            ];
        } else {
            $rules['productName'] = ['required', 'string', 'max:255'];
        }

        $validated = $this->validate($rules);

        if ($this->personMode === 'existing') {
            $personId = $validated['personId'];
        } else {
            $person = $resolver->resolveContact(
                (string) $this->organization->id,
                $this->newEmail !== '' ? $this->newEmail : null,
                $this->newFirstName !== '' ? $this->newFirstName : null,
                $this->newLastName !== '' ? $this->newLastName : null,
            );
            $personId = $person->id;
        }

        if ($this->productMode === 'existing') {
            $product = $this->organization->products()->findOrFail($validated['productId']);
            $productId = $product->id;
            $productName = $product->name;
        } else {
            $productId = null;
            $productName = $validated['productName'];
        }

        $this->organization->transactions()->create([
            'person_id' => $personId,
            'product_id' => $productId,
            'product_name' => $productName,
            'amount_cents' => (int) round($validated['amount'] * 100),
            'currency' => strtoupper($validated['currency']),
        ]);

        $this->reset(
            'personMode', 'personId', 'newFirstName', 'newLastName', 'newEmail',
            'productMode', 'productId', 'productName', 'amount', 'currency',
        );
        $this->currency = 'USD';
        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $transactions = $this->organization->transactions()
            ->with(['person.tenantPeople' => fn ($query) => $query->where('organization_id', $this->organization->id)])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('product_name', 'like', "%{$this->search}%")
                        ->orWhereHas('person.tenantPeople', function ($contact) {
                            $contact->where('organization_id', $this->organization->id)
                                ->where(function ($fields) {
                                    $fields->where('first_name', 'like', "%{$this->search}%")
                                        ->orWhere('last_name', 'like', "%{$this->search}%")
                                        ->orWhere('email', 'like', "%{$this->search}%");
                                });
                        });
                });
            })
            ->latest()
            ->paginate(20);

        return [
            'transactions' => $transactions,
            'tenantPeople' => $this->organization->tenantPeople()->with('person')->orderByDesc('first_seen_at')->get(),
            'products' => $this->organization->products()->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <div class="max-w-2xl">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Transactions') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Signups recorded for :organization.', ['organization' => $organization->name]) }}
        </p>
    </div>

    <div class="mt-6 max-w-2xl">
        <x-text-input wire:model.live="search" type="search" class="block w-full" :placeholder="__('Search by product or person...')" />
    </div>

    <div class="max-w-2xl mt-6 divide-y divide-gray-200 border-t border-gray-200">
        @forelse ($transactions as $transaction)
            @php $contact = $transaction->tenantContact(); @endphp
            <div class="flex items-center justify-between py-4">
                <div>
                    <div class="text-sm font-medium text-gray-900">
                        {{ $contact?->fullName() ?? $contact?->email ?? __('Unknown visitor') }}
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $transaction->product_name }}
                        &middot;
                        {{ $transaction->created_at->format('M j, Y') }}
                    </div>
                </div>

                <span class="text-sm font-medium text-gray-900">{{ $transaction->amountLabel() }}</span>
            </div>
        @empty
            <p class="py-4 text-sm text-gray-500">{{ __('No transactions yet.') }}</p>
        @endforelse
    </div>

    <div class="max-w-2xl mt-6">
        {{ $transactions->links() }}
    </div>

    @can('create', [\App\Models\Transaction::class, $organization])
        <div class="max-w-2xl mt-10">
            <h3 class="text-lg font-medium text-gray-900">
                {{ __('Record a Signup') }}
            </h3>

            <form wire:submit="record" class="mt-6 space-y-6">
                <div>
                    <x-input-label :value="__('Person')" />

                    <div class="mt-1 flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" wire:model.live="personMode" value="existing">
                            {{ __('Existing person') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" wire:model.live="personMode" value="new">
                            {{ __('New contact') }}
                        </label>
                    </div>

                    @if ($personMode === 'existing')
                        <select wire:model="personId" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Select a person...') }}</option>
                            @foreach ($tenantPeople as $tenantPerson)
                                <option value="{{ $tenantPerson->person_id }}">
                                    {{ $tenantPerson->fullName() ?? $tenantPerson->email ?? __('Visitor since :date', ['date' => $tenantPerson->first_seen_at->format('M j, Y')]) }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('personId')" class="mt-2" />
                    @else
                        <div class="mt-2 grid grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="newFirstName" :value="__('First Name')" />
                                <x-text-input wire:model="newFirstName" id="newFirstName" class="block mt-1 w-full" />
                            </div>
                            <div>
                                <x-input-label for="newLastName" :value="__('Last Name')" />
                                <x-text-input wire:model="newLastName" id="newLastName" class="block mt-1 w-full" />
                            </div>
                            <div>
                                <x-input-label for="newEmail" :value="__('Email')" />
                                <x-text-input wire:model="newEmail" id="newEmail" type="email" class="block mt-1 w-full" />
                                <x-input-error :messages="$errors->get('newEmail')" class="mt-2" />
                            </div>
                        </div>
                    @endif
                </div>

                <div>
                    <x-input-label :value="__('Product')" />

                    <div class="mt-1 flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" wire:model.live="productMode" value="existing">
                            {{ __('From catalog') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" wire:model.live="productMode" value="other">
                            {{ __('Not in catalog') }}
                        </label>
                    </div>

                    @if ($productMode === 'existing')
                        <select wire:model="productId" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Select a product...') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->priceLabel() }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('productId')" class="mt-2" />
                    @else
                        <x-text-input wire:model="productName" class="mt-2 block w-full" :placeholder="__('Product name')" />
                        <x-input-error :messages="$errors->get('productName')" class="mt-2" />
                    @endif
                </div>

                <div class="flex items-end gap-4">
                    <div>
                        <x-input-label for="amount" :value="__('Amount (USD)')" />
                        <x-text-input wire:model="amount" id="amount" class="block mt-1 w-28" />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="currency" :value="__('Currency')" />
                        <x-text-input wire:model="currency" id="currency" class="block mt-1 w-20" />
                        <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                    </div>

                    <x-primary-button>{{ __('Record Signup') }}</x-primary-button>
                </div>
            </form>
        </div>
    @endcan
</div>
