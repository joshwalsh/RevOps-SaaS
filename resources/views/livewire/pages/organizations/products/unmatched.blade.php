<?php

use App\Models\Organization;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Organization $organization;

    public ?string $matchingProductName = null;

    public string $selectedProductId = '';

    public ?string $creatingProductName = null;

    public string $newProductName = '';

    public string $newProductPrice = '0.00';

    public string $newProductCurrency = 'USD';

    /**
     * Resolve the organization and confirm the current user may view its transactions.
     */
    public function mount(Organization $organization): void
    {
        Gate::authorize('viewAny', [Transaction::class, $organization]);

        $this->organization = $organization;
    }

    /**
     * Open the inline match form for an unmatched product name.
     */
    public function matchStart(string $productName): void
    {
        $this->matchingProductName = $productName;
        $this->selectedProductId = '';
        $this->creatingProductName = null;
    }

    /**
     * Dismiss the inline match form without saving.
     */
    public function cancelMatch(): void
    {
        $this->reset('matchingProductName', 'selectedProductId');
    }

    /**
     * Connect every unmatched transaction recorded under the current
     * product name to the selected catalog product.
     */
    public function match(): void
    {
        Gate::authorize('match', [Transaction::class, $this->organization]);

        $validated = $this->validate([
            'selectedProductId' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('organization_id', $this->organization->id),
            ],
        ]);

        $this->organization->transactions()
            ->where('product_name', $this->matchingProductName)
            ->whereNull('product_id')
            ->update(['product_id' => $validated['selectedProductId']]);

        $this->reset('matchingProductName', 'selectedProductId');
    }

    /**
     * Open the inline create-product form for an unmatched product name,
     * prefilled with its name and its most recent transaction's price.
     */
    public function createStart(string $productName, int $amountCents, string $currency): void
    {
        $this->creatingProductName = $productName;
        $this->newProductName = $productName;
        $this->newProductPrice = number_format($amountCents / 100, 2, '.', '');
        $this->newProductCurrency = $currency;
        $this->matchingProductName = null;
    }

    /**
     * Dismiss the inline create-product form without saving.
     */
    public function cancelCreate(): void
    {
        $this->reset('creatingProductName', 'newProductName', 'newProductPrice', 'newProductCurrency');
    }

    /**
     * Add a new catalog product and connect every unmatched transaction
     * recorded under this product name to it.
     */
    public function createProduct(): void
    {
        Gate::authorize('create', [Product::class, $this->organization]);

        $validated = $this->validate([
            'newProductName' => ['required', 'string', 'max:255'],
            'newProductPrice' => ['required', 'numeric', 'min:0'],
            'newProductCurrency' => ['required', 'string', 'size:3'],
        ]);

        $product = $this->organization->products()->create([
            'name' => $validated['newProductName'],
            'price_cents' => (int) round($validated['newProductPrice'] * 100),
            'currency' => strtoupper($validated['newProductCurrency']),
        ]);

        $this->organization->transactions()
            ->where('product_name', $this->creatingProductName)
            ->whereNull('product_id')
            ->update(['product_id' => $product->id]);

        $this->reset('creatingProductName', 'newProductName', 'newProductPrice', 'newProductCurrency');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $unmatched = $this->organization->transactions()
            ->whereNull('product_id')
            ->orderByDesc('created_at')
            ->get(['product_name', 'amount_cents', 'currency'])
            ->groupBy('product_name')
            ->map(fn ($transactions, $productName) => (object) [
                'product_name' => $productName,
                'transactions_count' => $transactions->count(),
                'latest_amount_cents' => $transactions->first()->amount_cents,
                'latest_currency' => $transactions->first()->currency,
            ])
            ->sortKeys()
            ->values();

        return [
            'unmatched' => $unmatched,
            'products' => $this->organization->products()->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <div class="max-w-2xl">
        <a href="{{ route('organizations.products', $organization) }}" wire:navigate class="text-sm font-medium text-blue-700 hover:text-blue-800">
            &larr; {{ __('Back to Products') }}
        </a>

        <h2 class="mt-2 text-lg font-medium text-gray-900">
            {{ __('Match Unmatched Products') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('These product names were recorded on transactions for :organization but aren\'t connected to a catalog product. Match each one to keep reporting accurate.', ['organization' => $organization->name]) }}
        </p>
    </div>

    <div class="max-w-2xl mt-6 divide-y divide-gray-200 border-t border-gray-200">
        @forelse ($unmatched as $row)
            <div class="py-4">
                @if ($matchingProductName === $row->product_name)
                    <form wire:submit="match" wire:key="match-{{ $row->product_name }}" class="flex flex-wrap items-end gap-4">
                        <div class="flex-1 min-w-40">
                            <x-input-label :value="__('Product Name')" />
                            <div class="mt-1 text-sm text-gray-900">{{ $row->product_name }}</div>
                        </div>

                        <div class="flex-1 min-w-40">
                            <x-input-label for="selectedProductId" :value="__('Catalog Product')" />
                            <select wire:model="selectedProductId" id="selectedProductId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Select a product...') }}</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->priceLabel() }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('selectedProductId')" class="mt-2" />
                        </div>

                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                        <x-secondary-button type="button" wire:click="cancelMatch">{{ __('Cancel') }}</x-secondary-button>
                    </form>
                @elseif ($creatingProductName === $row->product_name)
                    <form wire:submit="createProduct" wire:key="create-{{ $row->product_name }}" class="flex flex-wrap items-end gap-4">
                        <div class="flex-1 min-w-40">
                            <x-input-label for="newProductName" :value="__('Name')" />
                            <x-text-input wire:model="newProductName" id="newProductName" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('newProductName')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="newProductPrice" :value="__('Price')" />
                            <x-text-input wire:model="newProductPrice" id="newProductPrice" class="block mt-1 w-28" />
                            <x-input-error :messages="$errors->get('newProductPrice')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="newProductCurrency" :value="__('Currency')" />
                            <x-text-input wire:model="newProductCurrency" id="newProductCurrency" class="block mt-1 w-20" />
                            <x-input-error :messages="$errors->get('newProductCurrency')" class="mt-2" />
                        </div>

                        <x-primary-button>{{ __('Create & Connect') }}</x-primary-button>
                        <x-secondary-button type="button" wire:click="cancelCreate">{{ __('Cancel') }}</x-secondary-button>
                    </form>
                @else
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $row->product_name }}</div>
                            <div class="text-sm text-gray-500">
                                {{ trans_choice(':count unmatched transaction|:count unmatched transactions', $row->transactions_count, ['count' => $row->transactions_count]) }}
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            @can('match', [\App\Models\Transaction::class, $organization])
                                <button type="button" wire:click="matchStart(@js($row->product_name))" class="text-sm font-medium text-blue-700 hover:text-blue-800">
                                    {{ __('Match') }}
                                </button>
                            @endcan

                            @can('create', [\App\Models\Product::class, $organization])
                                <button
                                    type="button"
                                    wire:click="createStart(@js($row->product_name), {{ $row->latest_amount_cents }}, @js($row->latest_currency))"
                                    class="text-sm font-medium text-blue-700 hover:text-blue-800"
                                >
                                    {{ __('Create Product') }}
                                </button>
                            @endcan
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="py-4 text-sm text-gray-500">{{ __('No unmatched products. Everything is connected to the catalog.') }}</p>
        @endforelse
    </div>
</div>
