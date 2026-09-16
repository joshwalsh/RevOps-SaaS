<?php

use App\Enums\TransactionStatus;
use App\Models\Organization;
use App\Models\Transaction;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Organization $organization;

    public Transaction $transaction;

    /**
     * Resolve the tenant's transaction (never a raw global Transaction
     * lookup, so one tenant can't probe another tenant's transactions) and
     * confirm the current user may view it.
     */
    public function mount(Organization $organization, int $transactionId): void
    {
        $this->transaction = $organization->transactions()
            ->with(['person.tenantPeople' => fn ($query) => $query->where('organization_id', $organization->id), 'product'])
            ->findOrFail($transactionId);

        Gate::authorize('view', $this->transaction);

        $this->organization = $organization;
    }
}; ?>

<div>
    <div class="max-w-2xl">
        <a href="{{ route('organizations.transactions', $organization) }}" wire:navigate class="text-sm font-medium text-blue-700 hover:text-blue-800">
            &larr; {{ __('Back to Transactions') }}
        </a>

        <div class="mt-2 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-medium text-gray-900">
                    {{ $transaction->product_name }}
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    {{ $transaction->created_at->format('M j, Y g:ia') }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                @if ($transaction->status === TransactionStatus::Fail)
                    <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700">
                        {{ __('Failed') }}
                    </span>
                @elseif ($transaction->status === TransactionStatus::Refund)
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">
                        {{ __('Refunded') }}
                    </span>
                @endif
                <span class="text-lg font-medium text-gray-900">{{ $transaction->amountLabel() }}</span>
            </div>
        </div>
    </div>

    <div class="max-w-2xl mt-10">
        <h3 class="text-lg font-medium text-gray-900">
            {{ __('Contact') }}
        </h3>

        <div class="mt-6 text-sm text-gray-700">
            @php $contact = $transaction->tenantContact(); @endphp
            @if ($contact)
                <a
                    href="{{ route('organizations.people.show', [$organization, $contact]) }}"
                    wire:navigate
                    class="font-medium text-blue-700 hover:text-blue-800"
                >
                    {{ $contact->full_name ?? $contact->email ?? __('Unnamed visitor') }}
                </a>
                @if ($contact->full_name && $contact->email)
                    <div class="mt-1 text-gray-500">{{ $contact->email }}</div>
                @endif
            @else
                <span class="text-gray-500">{{ __('Unknown visitor') }}</span>
            @endif
        </div>
    </div>

    <div class="max-w-2xl mt-10">
        <h3 class="text-lg font-medium text-gray-900">
            {{ __('Details') }}
        </h3>

        <dl class="mt-6 divide-y divide-gray-200 border-t border-gray-200 text-sm">
            <div class="flex items-center justify-between py-3">
                <dt class="text-gray-500">{{ __('Product') }}</dt>
                <dd class="text-gray-900">
                    @if ($transaction->product)
                        {{ $transaction->product->name }}
                    @else
                        {{ $transaction->product_name }} <span class="text-gray-400">({{ __('not in catalog') }})</span>
                    @endif
                </dd>
            </div>

            <div class="flex items-center justify-between py-3">
                <dt class="text-gray-500">{{ __('Status') }}</dt>
                <dd class="text-gray-900">{{ $transaction->status->name }}</dd>
            </div>

            <div class="flex items-center justify-between py-3">
                <dt class="text-gray-500">{{ __('Currency') }}</dt>
                <dd class="text-gray-900">{{ $transaction->currency }}</dd>
            </div>

            @if ($transaction->external_id)
                <div class="flex items-center justify-between py-3">
                    <dt class="text-gray-500">{{ __('External ID') }}</dt>
                    <dd class="text-gray-900">{{ $transaction->external_id }}</dd>
                </div>
            @endif
        </dl>
    </div>

    @if (! is_null($transaction->subtotal_cents) || ! is_null($transaction->total_cents) || ! is_null($transaction->fees_cents) || ! is_null($transaction->discount_cents))
        <div class="max-w-2xl mt-10">
            <h3 class="text-lg font-medium text-gray-900">
                {{ __('Accounting Breakdown') }}
            </h3>

            <dl class="mt-6 divide-y divide-gray-200 border-t border-gray-200 text-sm">
                @if (! is_null($transaction->subtotal_cents))
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-500">{{ __('Subtotal') }}</dt>
                        <dd class="text-gray-900">${{ number_format($transaction->subtotal_cents / 100, 2) }}</dd>
                    </div>
                @endif

                @if (! is_null($transaction->discount_cents))
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-500">{{ __('Discount') }}</dt>
                        <dd class="text-gray-900">-${{ number_format($transaction->discount_cents / 100, 2) }}</dd>
                    </div>
                @endif

                @if (! is_null($transaction->tax_cents))
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-500">{{ __('Tax') }}</dt>
                        <dd class="text-gray-900">${{ number_format($transaction->tax_cents / 100, 2) }}</dd>
                    </div>
                @endif

                @if (! is_null($transaction->total_cents))
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-500">{{ __('Total') }}</dt>
                        <dd class="text-gray-900">${{ number_format($transaction->total_cents / 100, 2) }}</dd>
                    </div>
                @endif

                @if (! is_null($transaction->fees_cents))
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-500">{{ __('Fees') }}</dt>
                        <dd class="text-gray-900">-${{ number_format($transaction->fees_cents / 100, 2) }}</dd>
                    </div>
                @endif

                <div class="flex items-center justify-between py-3">
                    <dt class="font-medium text-gray-900">{{ __('Net Amount') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $transaction->amountLabel() }}</dd>
                </div>
            </dl>
        </div>
    @endif
</div>
