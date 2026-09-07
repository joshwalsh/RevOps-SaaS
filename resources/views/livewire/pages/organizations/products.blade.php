<?php

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Organization $organization;

    public string $name = '';

    public string $price = '0.00';

    public string $currency = 'USD';

    public ?int $editingProductId = null;

    public string $editName = '';

    public string $editPrice = '0.00';

    public string $editCurrency = 'USD';

    /**
     * Resolve the organization and confirm the current user may view its catalog.
     */
    public function mount(Organization $organization): void
    {
        Gate::authorize('viewAny', [Product::class, $organization]);

        $this->organization = $organization;
    }

    /**
     * Add a new product to the organization's catalog.
     */
    public function create(): void
    {
        Gate::authorize('create', [Product::class, $this->organization]);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $this->organization->products()->create([
            'name' => $validated['name'],
            'price_cents' => (int) round($validated['price'] * 100),
            'currency' => strtoupper($validated['currency']),
        ]);

        $this->reset('name', 'price', 'currency');
        $this->currency = 'USD';
    }

    /**
     * Load a product into the inline edit form.
     */
    public function edit(int $productId): void
    {
        $product = $this->organization->products()->findOrFail($productId);

        Gate::authorize('update', $product);

        $this->editingProductId = $product->id;
        $this->editName = $product->name;
        $this->editPrice = number_format($product->price_cents / 100, 2, '.', '');
        $this->editCurrency = $product->currency;
    }

    /**
     * Save changes to the product being edited.
     */
    public function update(): void
    {
        $product = $this->organization->products()->findOrFail($this->editingProductId);

        Gate::authorize('update', $product);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editPrice' => ['required', 'numeric', 'min:0'],
            'editCurrency' => ['required', 'string', 'size:3'],
        ]);

        $product->update([
            'name' => $validated['editName'],
            'price_cents' => (int) round($validated['editPrice'] * 100),
            'currency' => strtoupper($validated['editCurrency']),
        ]);

        $this->cancelEdit();
    }

    /**
     * Dismiss the inline edit form without saving.
     */
    public function cancelEdit(): void
    {
        $this->reset('editingProductId', 'editName', 'editPrice', 'editCurrency');
    }

    /**
     * Delete a product. Existing transactions keep their recorded
     * product_name even after the catalog record is gone.
     */
    public function delete(int $productId): void
    {
        $product = $this->organization->products()->findOrFail($productId);

        Gate::authorize('delete', $product);

        $product->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'products' => $this->organization->products()
                ->withCount('transactions')
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div>
    <div class="max-w-2xl">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Products') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('The catalog of products people can sign up for at :organization.', ['organization' => $organization->name]) }}
        </p>
    </div>

    <div class="max-w-2xl mt-6 divide-y divide-gray-200 border-t border-gray-200">
        @forelse ($products as $product)
            <div class="py-4">
                @if ($editingProductId === $product->id)
                    <form wire:submit="update" wire:key="edit-{{ $product->id }}" class="flex flex-wrap items-end gap-4">
                        <div class="flex-1 min-w-40">
                            <x-input-label for="editName" :value="__('Name')" />
                            <x-text-input wire:model="editName" id="editName" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('editName')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="editPrice" :value="__('Price (USD)')" />
                            <x-text-input wire:model="editPrice" id="editPrice" class="block mt-1 w-28" />
                            <x-input-error :messages="$errors->get('editPrice')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="editCurrency" :value="__('Currency')" />
                            <x-text-input wire:model="editCurrency" id="editCurrency" class="block mt-1 w-20" />
                            <x-input-error :messages="$errors->get('editCurrency')" class="mt-2" />
                        </div>

                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                        <x-secondary-button type="button" wire:click="cancelEdit">{{ __('Cancel') }}</x-secondary-button>
                    </form>
                @else
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                            <div class="text-sm text-gray-500">
                                {{ $product->priceLabel() }}
                                &middot;
                                {{ trans_choice(':count signup|:count signups', $product->transactions_count, ['count' => $product->transactions_count]) }}
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            @can('update', $product)
                                <button type="button" wire:click="edit({{ $product->id }})" class="text-sm font-medium text-blue-700 hover:text-blue-800">
                                    {{ __('Edit') }}
                                </button>
                            @endcan

                            @can('delete', $product)
                                <x-danger-button
                                    wire:click="delete({{ $product->id }})"
                                    wire:confirm="{{ __('Delete this product? Existing transactions keep their recorded product name.') }}"
                                >
                                    {{ __('Delete') }}
                                </x-danger-button>
                            @endcan
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="py-4 text-sm text-gray-500">{{ __('No products yet.') }}</p>
        @endforelse
    </div>

    @can('create', [\App\Models\Product::class, $organization])
        <div class="max-w-2xl mt-10">
            <h3 class="text-lg font-medium text-gray-900">
                {{ __('Add Product') }}
            </h3>

            <form wire:submit="create" class="mt-6 flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-40">
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input wire:model="name" id="name" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="price" :value="__('Price (USD)')" />
                    <x-text-input wire:model="price" id="price" class="block mt-1 w-28" />
                    <x-input-error :messages="$errors->get('price')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="currency" :value="__('Currency')" />
                    <x-text-input wire:model="currency" id="currency" class="block mt-1 w-20" />
                    <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                </div>

                <x-primary-button>{{ __('Add Product') }}</x-primary-button>
            </form>
        </div>
    @endcan
</div>
